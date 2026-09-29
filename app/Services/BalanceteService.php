<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\BalanceteMensal;
use App\Models\CategoriaDespesa;
use App\Models\CategoriaReceita;
use App\Models\Centro;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Support\LinhasBalancete;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Balancete mensal no modelo oficial da Arquidiocese (Modulo 7, pedido do
 * cliente 2026-09-29). Ao contrario dos outros relatorios (so leitura), este
 * tem uma acao de escrita — fechar() — que gera Movimentos reais de despesa
 * para os 2 contributos calculados (Bispado, Dizimo), para poderem ser
 * conciliados com o banco quando a paroquia de facto transferir a Curia.
 *
 * Sempre por centro: cada Movimento pertence a 1 so centro (Modulo 4), por
 * isso nao existe "fecho agregado de paroquia inteira" — cada centro fecha o
 * seu Balancete.
 *
 * Percentagens configuraveis por paroquia (Paroquia::percentual_contributo_
 * bispado/dizimo, pedido do cliente 2026-09-30 — nada fica fixo no codigo).
 * Um mes ja fechado guarda em BalanceteMensal a percentagem USADA nesse
 * momento (percentual_bispado_usado/percentual_dizimo_usado) e os valores
 * ficam sempre lidos do Movimento de repasse ja gerado — nunca recalculados
 * com a percentagem actual, para o relatorio de um mes passado nao mudar
 * sozinho quando alguem edita a percentagem da paroquia mais tarde.
 */
class BalanceteService
{
    public static function calcular(int $ano, int $mes, int $centroId, int $paroquiaId): array
    {
        $movimentos = Movimento::where('centro_id', $centroId)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereYear('data_movimento', $ano)
            ->whereMonth('data_movimento', $mes)
            ->get(['tipo', 'valor', 'categoria_receita_id', 'categoria_despesa_id']);

        $peditorios = (float) $movimentos->where('tipo', TipoMovimento::Ofertorio)->sum('valor');
        $congruas = (float) $movimentos->where('tipo', TipoMovimento::Dizimo)->sum('valor');

        $porCategoriaReceita = [];
        foreach ($movimentos->where('tipo', TipoMovimento::Campanha) as $movimento) {
            if ($movimento->categoria_receita_id) {
                $porCategoriaReceita[$movimento->categoria_receita_id] =
                    ($porCategoriaReceita[$movimento->categoria_receita_id] ?? 0.0) + (float) $movimento->valor;
            }
        }

        $porCategoriaDespesa = [];
        foreach ($movimentos->where('tipo', TipoMovimento::DespesaCentro) as $movimento) {
            if ($movimento->categoria_despesa_id) {
                $porCategoriaDespesa[$movimento->categoria_despesa_id] =
                    ($porCategoriaDespesa[$movimento->categoria_despesa_id] ?? 0.0) + (float) $movimento->valor;
            }
        }

        // --- RECEITAS ---
        $linhasReceita = array_fill_keys(array_keys(LinhasBalancete::RECEITA), 0.0);
        $receitasDiversas = [];

        foreach (CategoriaReceita::where('paroquia_id', $paroquiaId)->where('status', 'ativo')->get() as $categoria) {
            $valor = $porCategoriaReceita[$categoria->id] ?? 0.0;

            if ($valor == 0.0) {
                continue;
            }

            if ($categoria->linha_balancete && array_key_exists($categoria->linha_balancete, $linhasReceita)) {
                $linhasReceita[$categoria->linha_balancete] += $valor;
            } else {
                $receitasDiversas[] = ['nome' => $categoria->nome, 'valor' => $valor];
            }
        }

        $intencoesMissa = $linhasReceita['1.3'];
        unset($linhasReceita['1.3']);

        $subtotalOrdinarias = $linhasReceita['1.1.1'] + $linhasReceita['1.1.2'] + $peditorios + $linhasReceita['1.1.4']
            + array_sum(array_column($receitasDiversas, 'valor'));
        $saldoAnterior = self::saldoMesAnterior($ano, $mes, $centroId);

        $totalReceitas = $subtotalOrdinarias + $congruas + $intencoesMissa + $saldoAnterior;

        // --- DESPESAS ---
        $linhasDespesa = array_fill_keys(array_keys(LinhasBalancete::DESPESA), 0.0);
        $outrasDespesas = [];
        $colectasObrigatorias = [];

        foreach (CategoriaDespesa::where('paroquia_id', $paroquiaId)->where('status', 'ativo')->get() as $categoria) {
            $valor = $porCategoriaDespesa[$categoria->id] ?? 0.0;

            if ($valor == 0.0) {
                continue;
            }

            $linha = $categoria->linha_balancete;

            // Reservadas (Bispado/Dizimo): geradas so por fechar(), nunca
            // lancadas manualmente — nao entram na soma dos lancamentos do
            // mes, sao a propria formula calculada abaixo.
            if ($linha === LinhasBalancete::BISPADO || $linha === LinhasBalancete::CONTRIBUTO_DIZIMO) {
                continue;
            }

            if ($linha === '2.3.4') {
                $colectasObrigatorias[] = ['nome' => $categoria->nome, 'valor' => $valor];
            } elseif ($linha && array_key_exists($linha, $linhasDespesa)) {
                $linhasDespesa[$linha] += $valor;
            } else {
                $outrasDespesas[] = ['nome' => $categoria->nome, 'valor' => $valor];
            }
        }

        $missasBinadas = $linhasDespesa['2.3.3'];
        unset($linhasDespesa['2.3.3'], $linhasDespesa['2.3.4']);

        $subtotalClero = $linhasDespesa['2.1.1'] + $linhasDespesa['2.1.2'] + $linhasDespesa['2.1.3'];
        $subtotalCorrentes = $linhasDespesa['2.2.1'] + $linhasDespesa['2.2.2'] + $linhasDespesa['2.2.3']
            + $linhasDespesa['2.2.4'] + $linhasDespesa['2.2.5'] + $linhasDespesa['2.2.6'] + $linhasDespesa['2.2.7']
            + array_sum(array_column($outrasDespesas, 'valor'));

        // O mes ja fechado tem os 2 contributos CONGELADOS no proprio
        // Movimento de repasse gerado por fechar() — nunca recalculados com
        // a percentagem actual da paroquia, para o relatorio de um mes
        // passado nao "mudar" sozinho quando alguem edita a percentagem
        // mais tarde (o Movimento ja pode ter sido transferido ao banco).
        // So um mes ainda aberto usa a percentagem actual, em tempo real.
        $balanceteFechado = BalanceteMensal::withoutGlobalScopes()
            ->where('centro_id', $centroId)
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->with(['movimentoBispado', 'movimentoDizimo'])
            ->first();

        if ($balanceteFechado) {
            $percentualBispado = (float) $balanceteFechado->percentual_bispado_usado;
            $percentualDizimo = (float) $balanceteFechado->percentual_dizimo_usado;
            $contributoBispado = (float) $balanceteFechado->movimentoBispado->valor;
            $contributoDizimo = (float) $balanceteFechado->movimentoDizimo->valor;
        } else {
            $paroquia = Paroquia::withoutGlobalScopes()->find($paroquiaId);
            $percentualBispado = (float) ($paroquia?->percentual_contributo_bispado ?? 20);
            $percentualDizimo = (float) ($paroquia?->percentual_contributo_dizimo ?? 60);
            $contributoBispado = round($totalReceitas * ($percentualBispado / 100), 2);
            $contributoDizimo = round($congruas * ($percentualDizimo / 100), 2);
        }

        $subtotalContributos = $contributoBispado + $contributoDizimo + $missasBinadas
            + array_sum(array_column($colectasObrigatorias, 'valor'));

        $totalDespesas = $subtotalClero + $subtotalCorrentes + $subtotalContributos;

        return [
            'receitas' => [
                'subsidios' => $linhasReceita['1.1.1'],
                'donativos' => $linhasReceita['1.1.2'],
                'peditorios' => $peditorios,
                'cartorio' => $linhasReceita['1.1.4'],
                'diversas' => $receitasDiversas,
                'subtotal_ordinarias' => $subtotalOrdinarias,
                'congruas' => $congruas,
                'intencoes_missa' => $intencoesMissa,
                'saldo_mes_anterior' => $saldoAnterior,
                'total' => $totalReceitas,
            ],
            'despesas' => [
                'subsidio_padres' => $linhasDespesa['2.1.1'],
                'estipendios' => $linhasDespesa['2.1.2'],
                'subsidio_casa_paroquial' => $linhasDespesa['2.1.3'],
                'subtotal_clero' => $subtotalClero,
                'salario_funcionarios' => $linhasDespesa['2.2.1'],
                'despesas_culto' => $linhasDespesa['2.2.2'],
                'transporte' => $linhasDespesa['2.2.3'],
                'energia' => $linhasDespesa['2.2.4'],
                'agua' => $linhasDespesa['2.2.5'],
                'internet' => $linhasDespesa['2.2.6'],
                'donativos_esmolas' => $linhasDespesa['2.2.7'],
                'outras_despesas' => $outrasDespesas,
                'subtotal_correntes' => $subtotalCorrentes,
                'contributo_bispado' => $contributoBispado,
                'contributo_dizimo' => $contributoDizimo,
                'percentual_bispado' => $percentualBispado,
                'percentual_dizimo' => $percentualDizimo,
                'missas_binadas' => $missasBinadas,
                'colectas_obrigatorias' => $colectasObrigatorias,
                'subtotal_contributos' => $subtotalContributos,
                'total' => $totalDespesas,
            ],
            'saldo_mes' => $totalReceitas - $totalDespesas,
            'fechado' => (bool) $balanceteFechado,
        ];
    }

    private static function saldoMesAnterior(int $ano, int $mes, int $centroId): float
    {
        $anoAnterior = $mes === 1 ? $ano - 1 : $ano;
        $mesAnterior = $mes === 1 ? 12 : $mes - 1;

        $balancete = BalanceteMensal::withoutGlobalScopes()
            ->where('centro_id', $centroId)
            ->where('ano', $anoAnterior)
            ->where('mes', $mesAnterior)
            ->first();

        return $balancete ? (float) $balancete->saldo_mes : 0.0;
    }

    /**
     * Fecha o Balancete do mes: calcula os totais, gera os Movimentos de
     * repasse (Bispado 20%, Dizimo) numa CategoriaDespesa reservada
     * (find-or-create por linha_balancete, uma so vez por paroquia) e grava
     * o registo de fecho. Idempotente: bloqueia um segundo fecho do mesmo
     * centro+mes (a unique key da BD e a ultima linha de defesa contra
     * concorrencia).
     */
    public static function fechar(
        int $ano,
        int $mes,
        int $centroId,
        int $usuarioId,
        int $metodoPagamentoId,
        ?int $bancoId,
        string $dataMovimento,
    ): BalanceteMensal {
        $centro = Centro::withoutGlobalScopes()->findOrFail($centroId);
        $paroquiaId = $centro->paroquia_id;

        return DB::transaction(function () use ($ano, $mes, $centroId, $paroquiaId, $usuarioId, $metodoPagamentoId, $bancoId, $dataMovimento) {
            $jaFechado = BalanceteMensal::withoutGlobalScopes()
                ->where('centro_id', $centroId)
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->lockForUpdate()
                ->exists();

            if ($jaFechado) {
                throw new RuntimeException('O Balancete deste mês já foi fechado para este centro.');
            }

            $dados = self::calcular($ano, $mes, $centroId, $paroquiaId);

            $categoriaBispado = CategoriaDespesa::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquiaId, 'linha_balancete' => LinhasBalancete::BISPADO],
                ['nome' => 'Contributo para o Bispado', 'status' => 'ativo']
            );
            $categoriaDizimo = CategoriaDespesa::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquiaId, 'linha_balancete' => LinhasBalancete::CONTRIBUTO_DIZIMO],
                ['nome' => 'Contributo do Dízimo à Arquidiocese', 'status' => 'ativo']
            );

            $movimentoBispado = Movimento::create([
                'paroquia_id' => $paroquiaId,
                'centro_id' => $centroId,
                'usuario_id' => $usuarioId,
                'metodo_pagamento_id' => $metodoPagamentoId,
                'banco_id' => $bancoId,
                'tipo' => TipoMovimento::DespesaCentro,
                'categoria_despesa_id' => $categoriaBispado->id,
                'valor' => $dados['despesas']['contributo_bispado'],
                'data_movimento' => $dataMovimento,
                'status_conciliacao' => StatusConciliacao::Aprovado,
            ]);

            $movimentoDizimo = Movimento::create([
                'paroquia_id' => $paroquiaId,
                'centro_id' => $centroId,
                'usuario_id' => $usuarioId,
                'metodo_pagamento_id' => $metodoPagamentoId,
                'banco_id' => $bancoId,
                'tipo' => TipoMovimento::DespesaCentro,
                'categoria_despesa_id' => $categoriaDizimo->id,
                'valor' => $dados['despesas']['contributo_dizimo'],
                'data_movimento' => $dataMovimento,
                'status_conciliacao' => StatusConciliacao::Aprovado,
            ]);

            return BalanceteMensal::create([
                'paroquia_id' => $paroquiaId,
                'centro_id' => $centroId,
                'usuario_id' => $usuarioId,
                'ano' => $ano,
                'mes' => $mes,
                'saldo_mes_anterior' => $dados['receitas']['saldo_mes_anterior'],
                'total_receitas' => $dados['receitas']['total'],
                'total_despesas' => $dados['despesas']['total'],
                'saldo_mes' => $dados['saldo_mes'],
                'percentual_bispado_usado' => $dados['despesas']['percentual_bispado'],
                'percentual_dizimo_usado' => $dados['despesas']['percentual_dizimo'],
                'movimento_bispado_id' => $movimentoBispado->id,
                'movimento_dizimo_id' => $movimentoDizimo->id,
                'fechado_em' => now(),
            ]);
        });
    }
}
