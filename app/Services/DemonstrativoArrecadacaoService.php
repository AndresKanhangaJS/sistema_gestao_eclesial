<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Movimento;

/**
 * Agrega receitas (dizimo, ofertorio, campanha) aprovadas por mes e por tipo,
 * para o Demonstrativo Unificado de Arrecadacao (Modulo 7).
 */
class DemonstrativoArrecadacaoService
{
    private const TIPOS_RECEITA = [
        TipoMovimento::Dizimo->value,
        TipoMovimento::Ofertorio->value,
        TipoMovimento::Campanha->value,
    ];

    public static function calcular(int $ano, ?int $centroId = null): array
    {
        $query = Movimento::whereIn('tipo', self::TIPOS_RECEITA)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereYear('data_movimento', $ano);

        if ($centroId) {
            $query->where('centro_id', $centroId);
        }

        $movimentos = $query->get(['tipo', 'valor', 'data_movimento']);

        $porMesTipo = [];
        foreach (range(1, 12) as $mes) {
            $porMesTipo[$mes] = [
                TipoMovimento::Dizimo->value => 0.0,
                TipoMovimento::Ofertorio->value => 0.0,
                TipoMovimento::Campanha->value => 0.0,
            ];
        }

        $porTipo = [
            TipoMovimento::Dizimo->value => 0.0,
            TipoMovimento::Ofertorio->value => 0.0,
            TipoMovimento::Campanha->value => 0.0,
        ];

        foreach ($movimentos as $movimento) {
            $mes = (int) $movimento->data_movimento->format('n');
            $tipo = $movimento->tipo->value;
            $valor = (float) $movimento->valor;

            $porMesTipo[$mes][$tipo] += $valor;
            $porTipo[$tipo] += $valor;
        }

        return [
            'por_mes_tipo' => $porMesTipo,
            'por_tipo' => $porTipo,
            'total' => array_sum($porTipo),
        ];
    }
}
