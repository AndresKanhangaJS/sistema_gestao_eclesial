<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Fiel;
use App\Models\Movimento;

/**
 * Segmenta os fieis por assiduidade do dizimo num ano (Assiduo 12/12,
 * Irregular 1-6, Inativo 0), independente do centro (soma todos os
 * dizimos aprovados do fiel no ano, mesmo que tenha mudado de centro).
 */
class FieisPorSituacaoService
{
    public static function calcular(int $ano, ?int $centroId = null): array
    {
        $fieisQuery = Fiel::query();

        if ($centroId) {
            $fieisQuery->whereHas('centros', fn ($q) => $q->where('centros.id', $centroId)->whereNull('fiel_centros.data_fim'));
        }

        $fieis = $fieisQuery->orderBy('nome')->get();

        $pagos = Movimento::where('tipo', TipoMovimento::Dizimo)
            ->where('ano_competencia', $ano)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->get()
            ->groupBy('fiel_id')
            ->map(fn ($grupo) => $grupo->pluck('mes_competencia')->unique()->count());

        return $fieis->map(function ($fiel) use ($pagos) {
            $totalPagos = $pagos->get($fiel->id, 0);

            $segmento = match (true) {
                $totalPagos === 12 => 'Assíduo',
                $totalPagos === 0 => 'Inativo',
                $totalPagos <= 6 => 'Irregular',
                default => null,
            };

            return [
                'fiel' => $fiel,
                'total_pagos' => $totalPagos,
                'segmento' => $segmento,
            ];
        })->all();
    }
}
