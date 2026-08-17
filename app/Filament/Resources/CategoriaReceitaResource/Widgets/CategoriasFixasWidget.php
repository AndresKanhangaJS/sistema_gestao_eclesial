<?php

namespace App\Filament\Resources\CategoriaReceitaResource\Widgets;

use App\Enums\TipoMovimento;
use App\Models\Movimento;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Dízimo, Ofertório e "Outras Contribuições (sem categoria)" são receitas de
 * tipo fixo do sistema (Dízimo/Ofertório têm regras próprias — unicidade
 * por fiel/mês, campo Fiel obrigatório, CLAUDE.md; a terceira é o resíduo de
 * `campanha` sem `categoria_receita_id`, sempre vai existir enquanto for
 * possível lançar uma receita sem escolher categoria específica), por isso
 * não são linhas editáveis/apagáveis em `categorias_receita`. O cliente
 * pediu para as ver mesmo assim junto das categorias personalizadas ao
 * gerir "Categorias de Receita" — mostradas aqui como cartões informativos,
 * acima da tabela editável (ver ListCategoriaReceitas::getHeaderWidgets()).
 *
 * Vive fora de app/Filament/Widgets (que o AdminPanelProvider descobre e
 * mostra no dashboard principal) de propósito — só é usado nesta página.
 */
class CategoriasFixasWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        $contar = fn (TipoMovimento $tipo, bool $semCategoriaReceita = false) => Movimento::where('tipo', $tipo)
            ->when($centroId, fn ($query) => $query->where('centro_id', $centroId))
            ->when($semCategoriaReceita, fn ($query) => $query->whereNull('categoria_receita_id'))
            ->count();

        return [
            Stat::make('Dízimo', $contar(TipoMovimento::Dizimo))
                ->description('Receitas lançadas (tipo fixo do sistema)')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Ofertório', $contar(TipoMovimento::Ofertorio))
                ->description('Receitas lançadas (tipo fixo do sistema)')
                ->icon('heroicon-o-banknotes')
                ->color('info'),
            Stat::make('Outras Contribuições (sem categoria)', $contar(TipoMovimento::Campanha, semCategoriaReceita: true))
                ->description('Receitas lançadas (tipo fixo do sistema)')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }
}
