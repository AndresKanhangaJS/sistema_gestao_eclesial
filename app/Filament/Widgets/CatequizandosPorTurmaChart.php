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

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

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
            ->with(['anoCatequetico', 'sacramentos'])
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
            // Sacramento(s) entre parenteses no fim do label — pedido do
            // utilizador: varias turmas podem ter o mesmo "1º Ano · Manhã",
            // só o conjunto de sacramentos distingue umas das outras.
            'labels' => $turmas->map(function (Turma $t) {
                $label = ($t->anoCatequetico?->nome ?? '—').' · '.ucfirst($t->periodo);
                $sacramentos = $t->sacramentos->pluck('nome')->implode(', ');

                return $sacramentos !== '' ? "{$label} ({$sacramentos})" : $label;
            })->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
