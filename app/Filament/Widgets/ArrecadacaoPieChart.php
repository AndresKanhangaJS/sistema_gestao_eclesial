<?php

namespace App\Filament\Widgets;

use App\Services\DemonstrativoArrecadacaoService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ArrecadacaoPieChart extends ChartWidget
{
    protected static ?string $heading = 'Proporção por tipo de receita';

    public ?int $ano = null;

    protected function getData(): array
    {
        $ano = $this->ano ?? now()->year;
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);

        return [
            'datasets' => [
                [
                    'data' => [
                        $dados['por_tipo']['dizimo'],
                        $dados['por_tipo']['ofertorio'],
                        $dados['por_tipo']['campanha'],
                    ],
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b'],
                ],
            ],
            'labels' => ['Dízimo', 'Ofertório', 'Outras Contribuições'],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
