<?php

namespace App\Filament\Pages\Relatorios;

use App\Filament\Concerns\FiltraPorCentro;
use App\Services\DemonstrativoArrecadacaoService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class DemonstrativoArrecadacao extends Page
{
    use FiltraPorCentro;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Demonstrativo de Receitas (Arrecadação)';

    protected static ?string $title = 'Demonstrativo Unificado de Receitas (Arrecadação)';

    protected static string $view = 'filament.pages.relatorios.demonstrativo-arrecadacao';

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']) ?? false;
    }

    public function mount(): void
    {
        $this->ano = (int) now()->year;
        $this->inicializarFiltroCentro();
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    #[Computed]
    public function dados(): array
    {
        return DemonstrativoArrecadacaoService::calcular($this->ano, $this->centroIdParaConsulta());
    }
}
