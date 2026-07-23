<?php

namespace App\Filament\Widgets;

use App\Models\Fiel;
use App\Services\DemonstrativoArrecadacaoService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Cards de estatistica rapida acima dos graficos de arrecadacao. tesoureiro_centro
 * fica restrito aos fieis vinculados ao seu proprio centro (mesmo criterio do
 * FielResource::getEloquentQuery()); os restantes papeis usam a ParoquiaScope
 * ja aplicada ao model Fiel.
 */
class EstatisticasGeraisWidget extends BaseWidget
{
    protected static ?int $sort = -10;

    /**
     * O pessoal da Catequese (papeis dedicados, nunca combinados com os
     * financeiros) tem o seu proprio dashboard — ver CatequeseEstatisticasWidget.
     */
    public static function canView(): bool
    {
        return ! (Auth::user()?->hasRole([
            'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese',
        ]) ?? false);
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = now()->year;

        $fieisQuery = Fiel::query()->where('status', 'ativo');

        if ($centroId) {
            $fieisQuery->whereHas(
                'centros',
                fn ($q) => $q->where('centros.id', $centroId)->whereNull('fiel_centros.data_fim')
            );
        }

        $totalFieisAtivos = $fieisQuery->count();

        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);

        $formatar = fn (float $valor) => 'Kz '.number_format($valor, 2, ',', '.');

        return [
            Stat::make('Fiéis Activos', $totalFieisAtivos)
                ->description('Dizimistas registados e activos')
                ->icon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Dízimos em '.$ano, $formatar($dados['por_tipo']['dizimo']))
                ->description('Total arrecadado')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Ofertórios em '.$ano, $formatar($dados['por_tipo']['ofertorio']))
                ->description('Total arrecadado')
                ->icon('heroicon-o-banknotes')
                ->color('info'),
            Stat::make('Outras Contribuições em '.$ano, $formatar($dados['por_tipo']['campanha']))
                ->description('Total arrecadado')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }
}
