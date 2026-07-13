<?php

namespace App\Filament\Widgets;

use App\Services\DemonstrativoArrecadacaoService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ArrecadacaoBarChart extends ChartWidget
{
    protected static ?string $heading = 'Arrecadação por mês';

    public ?int $ano = null;

    protected function getData(): array
    {
        $ano = $this->ano ?? now()->year;
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);

        return [
            'datasets' => [
                ['label' => 'Dízimo', 'data' => array_column($dados['por_mes_tipo'], 'dizimo'), 'backgroundColor' => '#3b82f6'],
                ['label' => 'Ofertório', 'data' => array_column($dados['por_mes_tipo'], 'ofertorio'), 'backgroundColor' => '#10b981'],
                ['label' => 'Outras Contribuições', 'data' => array_column($dados['por_mes_tipo'], 'campanha'), 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
