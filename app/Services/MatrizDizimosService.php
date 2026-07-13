<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Fiel;
use App\Models\Movimento;
use Carbon\Carbon;

/**
 * Calcula a matriz de assiduidade do dizimo (12 meses x fieis de um centro),
 * partilhada entre a pagina interactiva (Modulo 5) e o relatorio exportavel
 * (Modulo 7).
 *
 * A assiduidade e sempre por fiel, independente do centro (mesmo criterio do
 * FieisPorSituacaoService) — um fiel transferido a meio do ano pode ter pago
 * alguns meses num centro e outros noutro; contar so os pagamentos do centro
 * que esta a ser visto sub-contava o total e classificava erradamente como
 * Irregular quem tinha, de facto, os 12 meses pagos (so que espalhados por
 * dois centros).
 */
class MatrizDizimosService
{
    /**
     * Segmentacao (Modulo 4): Assiduo 12/12, Regular 7-11, Irregular 1-6,
     * Inactivo 0 — cobre sempre os 13 valores possiveis de total_pagos (0-12),
     * nunca fica por classificar.
     *
     * @return array<int, array{fiel: Fiel, meses: array<int, string>, total_pagos: int, segmento: string}>
     */
    public static function calcular(int $centroId, int $ano): array
    {
        $inicioAno = Carbon::createFromDate($ano, 1, 1)->startOfDay();
        $fimAno = Carbon::createFromDate($ano, 12, 31)->endOfDay();

        $fieis = Fiel::whereHas('centros', function ($q) use ($centroId, $inicioAno, $fimAno) {
            $q->where('centros.id', $centroId)
                ->where('fiel_centros.data_inicio', '<=', $fimAno)
                ->where(function ($q2) use ($inicioAno) {
                    $q2->whereNull('fiel_centros.data_fim')
                        ->orWhere('fiel_centros.data_fim', '>=', $inicioAno);
                });
        })
            ->with(['centros' => function ($q) use ($centroId) {
                $q->where('centros.id', $centroId);
            }])
            ->orderBy('nome')
            ->get();

        $pagos = Movimento::where('tipo', TipoMovimento::Dizimo)
            ->where('ano_competencia', $ano)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereIn('fiel_id', $fieis->pluck('id'))
            ->get()
            ->groupBy('fiel_id');

        $linhas = [];

        foreach ($fieis as $fiel) {
            $pagosDoFiel = $pagos->get($fiel->id, collect())->pluck('mes_competencia')->all();
            $meses = [];
            $totalPagos = 0;

            foreach (range(1, 12) as $mes) {
                if (in_array($mes, $pagosDoFiel, true)) {
                    $meses[$mes] = 'pago';
                    $totalPagos++;

                    continue;
                }

                $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfMonth();
                $fimMes = $inicioMes->copy()->endOfMonth();

                $vinculado = $fiel->centros->contains(function ($centro) use ($inicioMes, $fimMes) {
                    $inicio = Carbon::parse($centro->pivot->data_inicio);
                    $fim = $centro->pivot->data_fim ? Carbon::parse($centro->pivot->data_fim) : null;

                    return $inicio->lte($fimMes) && ($fim === null || $fim->gte($inicioMes));
                });

                $meses[$mes] = $vinculado ? 'em_aberto' : 'nao_vinculado';
            }

            $segmento = match (true) {
                $totalPagos === 12 => 'Assíduo',
                $totalPagos === 0 => 'Inactivo',
                $totalPagos <= 6 => 'Irregular',
                default => 'Regular',
            };

            $linhas[] = [
                'fiel' => $fiel,
                'meses' => $meses,
                'total_pagos' => $totalPagos,
                'segmento' => $segmento,
            ];
        }

        return $linhas;
    }
}
