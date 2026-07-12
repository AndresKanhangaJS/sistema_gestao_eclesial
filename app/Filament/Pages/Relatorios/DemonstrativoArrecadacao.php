<?php

namespace App\Filament\Pages\Relatorios;

use App\Filament\Widgets\ArrecadacaoBarChart;
use App\Filament\Widgets\ArrecadacaoPieChart;
use App\Services\DemonstrativoArrecadacaoService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class DemonstrativoArrecadacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Demonstrativo de Arrecadação';

    protected static ?string $title = 'Relatório — Demonstrativo Unificado de Arrecadação';

    protected static string $view = 'filament.pages.relatorios.demonstrativo-arrecadacao';

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']) ?? false;
    }

    public function mount(): void
    {
        $this->ano = (int) now()->year;
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ArrecadacaoBarChart::class,
            ArrecadacaoPieChart::class,
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        return ['ano' => $this->ano];
    }

    #[Computed]
    public function dados(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        return DemonstrativoArrecadacaoService::calcular($this->ano, $centroId);
    }
}
