<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoInscricao;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Inscricao;
use App\Models\Turma;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard proprio do pessoal da Catequese (papeis dedicados, nunca
 * combinados com os financeiros) — ver docs/modulos/catequese.md secc. 10.
 * coordenador_catequese_centro/secretario_catequese/tesoureiro_catequese
 * ficam restritos ao seu proprio centro; coordenador_catequese_paroquia ve
 * toda a paroquia (ParoquiaScope ja aplicada aos models).
 */
class CatequeseEstatisticasWidget extends BaseWidget
{
    private const PAPEIS_CATEQUESE = [
        'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese',
    ];

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(self::PAPEIS_CATEQUESE) ?? false;
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])
            ? $user->centro_id
            : null;

        $catequizandos = Catequizando::query()->where('status', 'ativo')
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->count();

        $turmas = Turma::query()->where('status', 'ativo')
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->count();

        $catequistas = Catequista::query()->where('ativo', true)
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->count();

        $inscricoesPendentes = Inscricao::query()->where('estado', EstadoInscricao::Inscrito)
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->count();

        return [
            Stat::make('Catequizandos Activos', $catequizandos)
                ->icon('heroicon-o-identification')
                ->color('primary'),
            Stat::make('Turmas Activas', $turmas)
                ->icon('heroicon-o-rectangle-group')
                ->color('info'),
            Stat::make('Catequistas', $catequistas)
                ->icon('heroicon-o-user')
                ->color('success'),
            Stat::make('Inscrições Pendentes', $inscricoesPendentes)
                ->description('Estado "Inscrito"')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning'),
        ];
    }
}
