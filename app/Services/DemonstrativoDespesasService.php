<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\CategoriaDespesa;
use App\Models\Movimento;

/**
 * Agrega despesas (tipo=despesa_centro) aprovadas por mes e por categoria,
 * para o Demonstrativo Unificado de Despesas (Modulo 7) — equivalente, do
 * lado das despesas, ao DemonstrativoArrecadacaoService das receitas.
 *
 * Ao contrario das receitas (3 tipos fixos), as categorias de despesa sao
 * dinamicas por paroquia, por isso o array e indexado por categoria_despesa_id
 * em vez de chaves fixas.
 */
class DemonstrativoDespesasService
{
    public static function calcular(int $ano, ?int $centroId = null): array
    {
        $categorias = CategoriaDespesa::where('status', 'ativo')
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $porMesCategoria = [];
        foreach (range(1, 12) as $mes) {
            $porMesCategoria[$mes] = $categorias->mapWithKeys(fn ($categoria) => [$categoria->id => 0.0])->all();
        }

        $porCategoria = $categorias->mapWithKeys(fn ($categoria) => [$categoria->id => 0.0])->all();

        $query = Movimento::where('tipo', TipoMovimento::DespesaCentro)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereYear('data_movimento', $ano);

        if ($centroId) {
            $query->where('centro_id', $centroId);
        }

        $movimentos = $query->get(['categoria_despesa_id', 'valor', 'data_movimento']);

        foreach ($movimentos as $movimento) {
            $categoriaId = $movimento->categoria_despesa_id;

            if ($categoriaId === null || ! isset($porCategoria[$categoriaId])) {
                continue;
            }

            $mes = (int) $movimento->data_movimento->format('n');
            $valor = (float) $movimento->valor;

            $porMesCategoria[$mes][$categoriaId] += $valor;
            $porCategoria[$categoriaId] += $valor;
        }

        return [
            'categorias' => $categorias,
            'por_mes_categoria' => $porMesCategoria,
            'por_categoria' => $porCategoria,
            'total' => array_sum($porCategoria),
        ];
    }
}
