<?php

namespace App\Filament\Pages\Relatorios;

use App\Services\DemonstrativoDespesasService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class DemonstrativoDespesas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Demonstrativo de Despesas';

    protected static ?string $title = 'Demonstrativo Unificado de Despesas';

    protected static string $view = 'filament.pages.relatorios.demonstrativo-despesas';

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']) ?? false;
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

    #[Computed]
    public function dados(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole(['tesoureiro_centro', 'coordenador_centro']) ? $user->centro_id : null;

        return DemonstrativoDespesasService::calcular($this->ano, $centroId);
    }
}
