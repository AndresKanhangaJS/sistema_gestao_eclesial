<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\User;
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
     * Resolve a lista de centros a consultar a partir do papel do utilizador
     * e de um centro_id pedido explicitamente (ex.: query string das rotas
     * de exportacao PDF/Excel). tesoureiro_centro fica sempre preso ao seu
     * proprio centro; os restantes veem "Todos os centros" que conseguem ver
     * quando nao pedem nenhum em concreto — ou pedem um que nao existe/nao
     * lhes pertence, caso em que a ParoquiaScope do Centro faz o exists()
     * falhar e cai-se de volta para "Todos" em vez de rebentar.
     *
     * @return array<int, int>
     */
    public static function centrosPermitidos(User $user, int|string|null $centroIdSolicitado): array
    {
        if ($user->hasRole('tesoureiro_centro')) {
            return [$user->centro_id];
        }

        if (filled($centroIdSolicitado) && Centro::whereKey((int) $centroIdSolicitado)->exists()) {
            return [(int) $centroIdSolicitado];
        }

        return Centro::orderBy('nome')->pluck('id')->all();
    }

    /**
     * Segmentacao (Modulo 4): Assiduo 12/12, Regular 7-11, Irregular 1-6,
     * Inactivo 0 — cobre sempre os 13 valores possiveis de total_pagos (0-12),
     * nunca fica por classificar.
     *
     * @param  array<int, int>  $centroIds  Um ou mais centros (ex.: um administrador_paroquial
     *                                      a ver "Todos os centros" da sua paróquia de uma vez).
     * @return array<int, array{fiel: Fiel, meses: array<int, string>, total_pagos: int, segmento: string}>
     */
    public static function calcular(array $centroIds, int $ano): array
    {
        $inicioAno = Carbon::createFromDate($ano, 1, 1)->startOfDay();
        $fimAno = Carbon::createFromDate($ano, 12, 31)->endOfDay();

        $fieis = Fiel::whereHas('centros', function ($q) use ($centroIds, $inicioAno, $fimAno) {
            $q->whereIn('centros.id', $centroIds)
                ->where('fiel_centros.data_inicio', '<=', $fimAno)
                ->where(function ($q2) use ($inicioAno) {
                    $q2->whereNull('fiel_centros.data_fim')
                        ->orWhere('fiel_centros.data_fim', '>=', $inicioAno);
                });
        })
            ->with(['centros' => function ($q) use ($centroIds) {
                $q->whereIn('centros.id', $centroIds);
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
