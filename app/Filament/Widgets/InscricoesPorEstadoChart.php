<?php

namespace App\Filament\Widgets;

use App\Models\Inscricao;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class InscricoesPorEstadoChart extends ChartWidget
{
    private const PAPEIS_CATEQUESE = [
        'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese',
    ];

    protected static ?string $heading = 'Inscrições por Estado';

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

        $porEstado = Inscricao::query()
            ->when($centroId, fn ($q) => $q->where('centro_id', $centroId))
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $labels = [
            'inscrito' => 'Inscrito',
            'aprovado' => 'Aprovado',
            'reprovado' => 'Reprovado',
            'desistente' => 'Desistente',
            'cancelado' => 'Cancelado',
        ];

        return [
            'datasets' => [
                [
                    'data' => collect($labels)->keys()->map(fn ($estado) => $porEstado[$estado] ?? 0)->all(),
                    'backgroundColor' => ['#f59e0b', '#10b981', '#ef4444', '#6b7280', '#9ca3af'],
                ],
            ],
            'labels' => array_values($labels),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
