<?php

namespace App\Filament\Widgets;

use App\Services\DemonstrativoDespesasService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class DespesasPieChart extends ChartWidget
{
    protected static ?string $heading = 'Proporção por categoria de despesa';

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    private const CORES = ['#ef4444', '#f97316', '#eab308', '#84cc16', '#06b6d4', '#6366f1', '#a855f7', '#ec4899'];

    public ?int $ano = null;

    // Ver ArrecadacaoBarChart::$centroId.
    public ?int $centroId = null;

    /** @see EstatisticasGeraisWidget::canView() */
    public static function canView(): bool
    {
        return ! (Auth::user()?->hasRole([
            'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese',
            'secretario_centro',
        ]) ?? false);
    }

    protected function getData(): array
    {
        $ano = $this->ano ?? now()->year;
        $user = Auth::user();
        $centroId = $this->centroId ?? ($user?->hasRole(['tesoureiro_centro', 'coordenador_centro']) ? $user->centro_id : null);

        $dados = DemonstrativoDespesasService::calcular($ano, $centroId);

        $categorias = $dados['categorias']->values();

        return [
            'datasets' => [
                [
                    'data' => $categorias->map(fn ($categoria) => $dados['por_categoria'][$categoria->id])->all(),
                    'backgroundColor' => $categorias->map(fn ($categoria, $indice) => self::CORES[$indice % count(self::CORES)])->all(),
                ],
            ],
            'labels' => $categorias->pluck('nome')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
