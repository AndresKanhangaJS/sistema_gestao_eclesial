<?php

namespace Database\Seeders;

use App\Models\Banco;
use App\Models\CategoriaDespesa;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstracao (bancos, metodos, categorias, fieis, vinculos e
 * movimentos) para o painel e os relatorios terem conteudo real a mostrar.
 * Idempotente: pode correr varias vezes sem duplicar nada.
 */
class DemoDataSeeder extends Seeder
{
    private int $ano;

    public function run(): void
    {
        $this->ano = (int) now()->year;

        $paroquia = Paroquia::where('nome', 'Paroquia de Teste')->firstOrFail();
        $centroPrincipal = Centro::withoutGlobalScopes()
            ->where('paroquia_id', $paroquia->id)
            ->where('nome', 'Centro de Teste')
            ->firstOrFail();
        $centroSecundario = Centro::withoutGlobalScopes()->firstOrCreate(
            ['paroquia_id' => $paroquia->id, 'nome' => 'Centro Santo António']
        );
        $tesoureiro = User::where('email', 'tesoureiro_paroquial@sge.local')->firstOrFail();

        $bancos = $this->seedBancos($paroquia);
        $metodos = $this->seedMetodosPagamento();
        $categorias = $this->seedCategoriasDespesa($paroquia);

        $fieis = $this->seedFieisEVinculos($paroquia, $centroPrincipal, $centroSecundario);

        $this->seedDizimos($fieis, $tesoureiro, $metodos['Numerário']);
        $this->seedOfertoriosCampanhas($paroquia, $centroPrincipal, $tesoureiro, $metodos, $bancos);
        $this->seedDespesas($paroquia, $centroPrincipal, $tesoureiro, $metodos['Numerário'], $categorias);
        $this->seedComprovativoPendenteAntigo($paroquia, $centroPrincipal, $tesoureiro, $metodos['Transferência Bancária']);
    }

    /** @return array<string, Banco> indexado por sigla */
    private function seedBancos(Paroquia $paroquia): array
    {
        $dados = [
            ['nome_banco' => 'Banco de Fomento Angola', 'sigla' => 'BFA', 'numero_conta' => '111222333', 'iban' => 'AO06000000111222333'],
            ['nome_banco' => 'Banco Angolano de Investimentos', 'sigla' => 'BAI', 'numero_conta' => '444555666', 'iban' => 'AO06000000444555666'],
            ['nome_banco' => 'Banco BIC', 'sigla' => 'BIC', 'numero_conta' => '777888999', 'iban' => 'AO06000000777888999'],
        ];

        $bancos = [];
        foreach ($dados as $d) {
            $bancos[$d['sigla']] = Banco::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome_banco' => $d['nome_banco']],
                array_merge($d, ['paroquia_id' => $paroquia->id])
            );
        }

        return $bancos;
    }

    /** @return array<string, MetodoPagamento> indexado por nome */
    private function seedMetodosPagamento(): array
    {
        $dados = [
            ['nome' => 'Numerário', 'exige_comprovativo' => false],
            ['nome' => 'Transferência Bancária', 'exige_comprovativo' => true],
            ['nome' => 'Depósito Bancário', 'exige_comprovativo' => true],
            ['nome' => 'Cheque', 'exige_comprovativo' => true],
        ];

        $metodos = [];
        foreach ($dados as $d) {
            $metodos[$d['nome']] = MetodoPagamento::firstOrCreate(['nome' => $d['nome']], $d);
        }

        return $metodos;
    }

    /** @return array<int, CategoriaDespesa> */
    private function seedCategoriasDespesa(Paroquia $paroquia): array
    {
        $nomes = ['Manutenção', 'Eletricidade e Água', 'Material de Escritório', 'Transporte', 'Eventos Paroquiais'];

        return collect($nomes)
            ->map(fn ($nome) => CategoriaDespesa::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome' => $nome]
            ))
            ->all();
    }

    /**
     * @return array<int, array{fiel: Fiel, perfil: string, transferido: bool}>
     */
    private function seedFieisEVinculos(Paroquia $paroquia, Centro $centroPrincipal, Centro $centroSecundario): array
    {
        $perfis = [
            ...array_fill(0, 10, 'assiduo'),
            ...array_fill(0, 8, 'irregular'),
            ...array_fill(0, 5, 'inativo'),
            ...array_fill(0, 2, 'nao_vinculado'),
        ];

        $fieis = [];

        foreach ($perfis as $i => $perfil) {
            $numero = $i + 1;
            $codigo = 'F' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);

            $fiel = Fiel::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'codigo_dizimista' => $codigo],
                [
                    'nome' => fake('pt_PT')->name(),
                    'telefone' => '9' . fake()->numerify('########'),
                    'email' => "fiel{$numero}@sge.local",
                    'data_nascimento' => fake()->dateTimeBetween('-70 years', '-18 years'),
                    'status' => 'ativo',
                ]
            );

            $transferido = $perfil === 'assiduo' && $numero <= 2;

            if ($perfil !== 'nao_vinculado') {
                if ($transferido) {
                    $fiel->centros()->syncWithoutDetaching([
                        $centroPrincipal->id => ['data_inicio' => "{$this->ano}-01-01", 'data_fim' => "{$this->ano}-06-30", 'principal' => true],
                    ]);

                    $jaTransferido = $fiel->centros()->withoutGlobalScopes()
                        ->wherePivot('centro_id', $centroSecundario->id)
                        ->wherePivot('data_inicio', "{$this->ano}-07-01")
                        ->exists();

                    if (! $jaTransferido) {
                        $fiel->centros()->attach($centroSecundario->id, [
                            'data_inicio' => "{$this->ano}-07-01",
                            'principal' => true,
                            'motivo_transferencia' => 'Mudança de residência',
                        ]);
                    }
                } else {
                    $fiel->centros()->syncWithoutDetaching([
                        $centroPrincipal->id => ['data_inicio' => "{$this->ano}-01-01", 'principal' => true],
                    ]);
                }
            }

            $fieis[$numero] = ['fiel' => $fiel, 'perfil' => $perfil, 'transferido' => $transferido];
        }

        return $fieis;
    }

    /**
     * Assiduos: 12/12 aprovados. Irregulares: 1-6 aprovados (+ alguns
     * pendente/rejeitado extra, para os ecras de conciliacao). Inativos e
     * nao_vinculados: sem nenhum dizimo.
     */
    private function seedDizimos(array $fieis, User $tesoureiro, MetodoPagamento $numerario): void
    {
        // numero => [mes => status] dos meses aprovados dos irregulares (11-18)
        $planoIrregulares = [
            11 => [1 => 'aprovado', 2 => 'pendente'],
            12 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'rejeitado'],
            13 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'aprovado'],
            14 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'aprovado', 4 => 'aprovado'],
            15 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'aprovado', 4 => 'aprovado', 5 => 'aprovado'],
            16 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'aprovado', 4 => 'aprovado', 5 => 'aprovado', 6 => 'aprovado'],
            17 => [1 => 'aprovado', 2 => 'aprovado'],
            18 => [1 => 'aprovado', 2 => 'aprovado', 3 => 'aprovado', 4 => 'aprovado'],
        ];

        foreach ($fieis as $numero => $dados) {
            $fiel = $dados['fiel'];
            $perfil = $dados['perfil'];

            if (in_array($perfil, ['nao_vinculado', 'inativo'], true)) {
                continue;
            }

            $planoMeses = $perfil === 'assiduo'
                ? array_fill_keys(range(1, 12), 'aprovado')
                : $planoIrregulares[$numero];

            foreach ($planoMeses as $mes => $status) {
                $existe = Movimento::withoutGlobalScopes()
                    ->where('fiel_id', $fiel->id)
                    ->where('tipo', 'dizimo')
                    ->where('ano_competencia', $this->ano)
                    ->where('mes_competencia', $mes)
                    ->exists();

                if ($existe) {
                    continue;
                }

                $centro = ($dados['transferido'] && $mes >= 7) ? 'secundario' : 'principal';

                Movimento::create([
                    'paroquia_id' => $fiel->paroquia_id,
                    'centro_id' => $this->centroIdParaFiel($fiel, $centro),
                    'usuario_id' => $tesoureiro->id,
                    'fiel_id' => $fiel->id,
                    'metodo_pagamento_id' => $numerario->id,
                    'tipo' => 'dizimo',
                    'valor' => fake()->numberBetween(3000, 8000),
                    'ano_competencia' => $this->ano,
                    'mes_competencia' => $mes,
                    'data_movimento' => Carbon::createFromDate($this->ano, $mes, 5),
                    'status_conciliacao' => $status,
                    'motivo_rejeicao' => $status === 'rejeitado' ? 'Valor não confere com o registado no talão.' : null,
                ]);
            }
        }
    }

    private function centroIdParaFiel(Fiel $fiel, string $qual): int
    {
        $centro = $fiel->centros()->withoutGlobalScopes()
            ->when($qual === 'principal', fn ($q) => $q->where('centros.nome', 'Centro de Teste'))
            ->when($qual === 'secundario', fn ($q) => $q->where('centros.nome', 'Centro Santo António'))
            ->first();

        return $centro->id;
    }

    private function seedOfertoriosCampanhas(Paroquia $paroquia, Centro $centro, User $tesoureiro, array $metodos, array $bancos): void
    {
        $metodoNomes = array_keys($metodos);
        $bancoSiglas = array_keys($bancos);

        for ($i = 1; $i <= 15; $i++) {
            $mes = (($i - 1) % 12) + 1;
            $tipo = $i % 3 === 0 ? 'campanha' : 'ofertorio';
            // Dia = $i (unico por iteracao, mesmo com o mes a repetir-se no
            // ciclo) para servir de chave natural de idempotencia abaixo.
            $dataMovimento = Carbon::createFromDate($this->ano, $mes, $i);

            $existe = Movimento::withoutGlobalScopes()
                ->where('paroquia_id', $paroquia->id)
                ->where('tipo', $tipo)
                ->whereDate('data_movimento', $dataMovimento)
                ->exists();

            if ($existe) {
                continue;
            }

            $metodo = $metodos[$metodoNomes[$i % count($metodoNomes)]];
            $temBanco = $i % 2 === 0;
            $banco = $temBanco ? $bancos[$bancoSiglas[$i % count($bancoSiglas)]] : null;
            $referencia = $temBanco ? 'REF-DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT) : null;

            Movimento::create([
                'paroquia_id' => $paroquia->id,
                'centro_id' => $centro->id,
                'usuario_id' => $tesoureiro->id,
                'metodo_pagamento_id' => $metodo->id,
                'banco_id' => $banco?->id,
                'tipo' => $tipo,
                'valor' => 1000 + ($i * 150),
                'data_movimento' => $dataMovimento,
                'comprovativo_path' => $metodo->exige_comprovativo ? "comprovativos/demo-{$i}.pdf" : null,
                'numero_referencia_bancaria' => $referencia,
                'status_conciliacao' => 'aprovado',
            ]);
        }
    }

    private function seedDespesas(Paroquia $paroquia, Centro $centro, User $tesoureiro, MetodoPagamento $numerario, array $categorias): void
    {
        $valores = [200, 850, 1200, 1800, 2500, 3200, 60000, 75000];

        foreach ($valores as $i => $valor) {
            $mes = ($i % 12) + 1;
            $categoria = $categorias[$i % count($categorias)];

            $jaExiste = Movimento::withoutGlobalScopes()
                ->where('paroquia_id', $paroquia->id)
                ->where('tipo', 'despesa_centro')
                ->where('valor', $valor)
                ->where('categoria_despesa_id', $categoria->id)
                ->exists();

            if ($jaExiste) {
                continue;
            }

            // DatabaseSeeder usa WithoutModelEvents (desliga o MovimentoObserver
            // durante o seed), por isso replicamos aqui a mesma regra de
            // aprovacao automatica por limite (config/sge.php).
            $status = $valor <= (float) config('sge.valor_aprovacao_despesa') ? 'aprovado' : 'pendente';

            Movimento::create([
                'paroquia_id' => $paroquia->id,
                'centro_id' => $centro->id,
                'usuario_id' => $tesoureiro->id,
                'metodo_pagamento_id' => $numerario->id,
                'categoria_despesa_id' => $categoria->id,
                'tipo' => 'despesa_centro',
                'valor' => $valor,
                'data_movimento' => Carbon::createFromDate($this->ano, $mes, 20),
                'status_conciliacao' => $status,
            ]);
        }
    }

    private function seedComprovativoPendenteAntigo(Paroquia $paroquia, Centro $centro, User $tesoureiro, MetodoPagamento $metodoExige): void
    {
        $existe = Movimento::withoutGlobalScopes()
            ->where('paroquia_id', $paroquia->id)
            ->where('numero_referencia_bancaria', 'REF-DEMO-PENDENTE-48H')
            ->exists();

        if ($existe) {
            return;
        }

        $movimento = Movimento::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'usuario_id' => $tesoureiro->id,
            'metodo_pagamento_id' => $metodoExige->id,
            'tipo' => 'ofertorio',
            'valor' => 500,
            'data_movimento' => now()->subDays(3),
            'numero_referencia_bancaria' => 'REF-DEMO-PENDENTE-48H',
            'status_conciliacao' => 'pendente',
        ]);

        $movimento->timestamps = false;
        $movimento->created_at = now()->subHours(72);
        $movimento->save();
    }
}
