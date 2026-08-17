<?php

namespace App\Filament\Widgets;

use App\Services\DemonstrativoArrecadacaoService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ArrecadacaoPieChart extends ChartWidget
{
    protected static ?string $heading = 'Proporção por categoria de receita';

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    private const CORES = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

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

        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);

        $categorias = $dados['categorias']->values();

        return [
            'datasets' => [
                [
                    'data' => $categorias->map(fn (array $categoria) => $dados['por_categoria'][$categoria['chave']])->all(),
                    'backgroundColor' => $categorias->map(fn (array $categoria, int $indice) => self::CORES[$indice % count(self::CORES)])->all(),
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
