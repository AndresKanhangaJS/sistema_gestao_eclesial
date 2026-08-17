<?php

namespace App\Filament\Widgets;

use App\Services\DemonstrativoArrecadacaoService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ArrecadacaoBarChart extends ChartWidget
{
    protected static ?string $heading = 'Receitas (Arrecadação) por mês';

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    private const CORES = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];

    public ?int $ano = null;

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
        $centroId = $user?->hasRole(['tesoureiro_centro', 'coordenador_centro']) ? $user->centro_id : null;

        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);

        $datasets = $dados['categorias']->values()->map(fn (array $categoria, int $indice) => [
            'label' => $categoria['nome'],
            'data' => array_column($dados['por_mes_categoria'], $categoria['chave']),
            'backgroundColor' => self::CORES[$indice % count(self::CORES)],
        ])->all();

        return [
            'datasets' => $datasets,
            'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
