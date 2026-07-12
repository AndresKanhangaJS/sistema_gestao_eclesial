<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Movimento;

/**
 * Balanco de Receitas vs Despesas: Receitas - Despesas = Saldo, por mes,
 * so considerando movimentos aprovados (Modulo 7).
 */
class BalancoReceitasDespesasService
{
    private const TIPOS_RECEITA = [
        TipoMovimento::Dizimo->value,
        TipoMovimento::Ofertorio->value,
        TipoMovimento::Campanha->value,
    ];

    public static function calcular(int $ano, ?int $centroId = null): array
    {
        $query = Movimento::where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereYear('data_movimento', $ano);

        if ($centroId) {
            $query->where('centro_id', $centroId);
        }

        $movimentos = $query->get(['tipo', 'valor', 'data_movimento']);

        $porMes = [];
        foreach (range(1, 12) as $mes) {
            $porMes[$mes] = ['receitas' => 0.0, 'despesas' => 0.0];
        }

        foreach ($movimentos as $movimento) {
            $mes = (int) $movimento->data_movimento->format('n');
            $valor = (float) $movimento->valor;

            if (in_array($movimento->tipo->value, self::TIPOS_RECEITA, true)) {
                $porMes[$mes]['receitas'] += $valor;
            } else {
                $porMes[$mes]['despesas'] += $valor;
            }
        }

        foreach ($porMes as $mes => $valores) {
            $porMes[$mes]['saldo'] = $valores['receitas'] - $valores['despesas'];
        }

        $totalReceitas = array_sum(array_column($porMes, 'receitas'));
        $totalDespesas = array_sum(array_column($porMes, 'despesas'));

        return [
            'por_mes' => $porMes,
            'total_receitas' => $totalReceitas,
            'total_despesas' => $totalDespesas,
            'saldo' => $totalReceitas - $totalDespesas,
        ];
    }
}
