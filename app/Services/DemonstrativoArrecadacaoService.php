<?php

namespace App\Services;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\CategoriaReceita;
use App\Models\Movimento;

/**
 * Agrega receitas (dizimo, ofertorio, campanha) aprovadas por mes e por
 * categoria, para o Demonstrativo Unificado de Receitas/Arrecadacao
 * (Modulo 7). Dizimo e Ofertorio sao categorias fixas do sistema; as
 * restantes vem de CategoriaReceita (Casamentos, Baptizados, ...),
 * registadas pelo cliente — pedido do cliente para deixarem de estar
 * escondidas atras de um unico bucket "Outras Contribuicoes" (mesmo
 * espirito do DemonstrativoDespesasService, do lado das despesas).
 */
class DemonstrativoArrecadacaoService
{
    private const CHAVE_DIZIMO = 'dizimo';

    private const CHAVE_OFERTORIO = 'ofertorio';

    private const CHAVE_OUTRAS_SEM_CATEGORIA = 'outras_sem_categoria';

    public static function calcular(int $ano, ?int $centroId = null): array
    {
        $categorias = collect([
            ['chave' => self::CHAVE_DIZIMO, 'nome' => 'Dízimo'],
            ['chave' => self::CHAVE_OFERTORIO, 'nome' => 'Ofertório'],
        ])
            ->concat(
                CategoriaReceita::where('status', 'ativo')
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (CategoriaReceita $categoria) => ['chave' => "cr_{$categoria->id}", 'nome' => $categoria->nome])
            )
            ->push(['chave' => self::CHAVE_OUTRAS_SEM_CATEGORIA, 'nome' => 'Outras Contribuições (sem categoria)']);

        $porMesCategoria = [];
        foreach (range(1, 12) as $mes) {
            $porMesCategoria[$mes] = $categorias->mapWithKeys(fn (array $categoria) => [$categoria['chave'] => 0.0])->all();
        }

        $porCategoria = $categorias->mapWithKeys(fn (array $categoria) => [$categoria['chave'] => 0.0])->all();

        $query = Movimento::whereIn('tipo', [
            TipoMovimento::Dizimo->value,
            TipoMovimento::Ofertorio->value,
            TipoMovimento::Campanha->value,
        ])
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->whereYear('data_movimento', $ano);

        if ($centroId) {
            $query->where('centro_id', $centroId);
        }

        $movimentos = $query->get(['tipo', 'categoria_receita_id', 'valor', 'data_movimento']);

        foreach ($movimentos as $movimento) {
            $chave = match (true) {
                $movimento->tipo === TipoMovimento::Dizimo => self::CHAVE_DIZIMO,
                $movimento->tipo === TipoMovimento::Ofertorio => self::CHAVE_OFERTORIO,
                $movimento->categoria_receita_id !== null => "cr_{$movimento->categoria_receita_id}",
                default => self::CHAVE_OUTRAS_SEM_CATEGORIA,
            };

            // Categoria entretanto desactivada depois do lancamento — cai no
            // residual "sem categoria" em vez de perder o valor do relatorio.
            if (! array_key_exists($chave, $porCategoria)) {
                $chave = self::CHAVE_OUTRAS_SEM_CATEGORIA;
            }

            $mes = (int) $movimento->data_movimento->format('n');
            $valor = (float) $movimento->valor;

            $porMesCategoria[$mes][$chave] += $valor;
            $porCategoria[$chave] += $valor;
        }

        return [
            'categorias' => $categorias,
            'por_mes_categoria' => $porMesCategoria,
            'por_categoria' => $porCategoria,
            'total' => array_sum($porCategoria),
        ];
    }
}
