<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoInscricaoTurma;
use App\Models\Turma;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class CatequizandosPorTurmaChart extends ChartWidget
{
    private const PAPEIS_CATEQUESE = [
        'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese',
    ];

    protected static ?string $heading = 'Catequizandos por Turma';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole(self::PAPEIS_CATEQUESE) ?? false;
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])
            ? $user->centro_id
            : null;

        $turmas = Turma::query()->where('status', 'ativo')
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->with('anoCatequetico')
            // wherePivot() nao resolve correctamente dentro do subquery de
            // withCount() nesta versao do Filament (gera "pivot = status" em
            // vez de qualificar a tabela) — nome da coluna qualificado a
            // resolver o problema.
            ->withCount(['inscricoes as catequizandos_activos' => fn ($q) => $q->where('inscricao_turma.status', EstadoInscricaoTurma::Ativo->value)])
            ->get();

        return [
            'datasets' => [
                ['label' => 'Catequizandos', 'data' => $turmas->pluck('catequizandos_activos')->all(), 'backgroundColor' => '#3b82f6'],
            ],
            'labels' => $turmas->map(fn (Turma $t) => ($t->anoCatequetico?->nome ?? '—').' · '.ucfirst($t->periodo))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
