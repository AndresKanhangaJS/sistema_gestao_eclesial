<?php

namespace App\Filament\Pages\Relatorios;

use App\Services\BalancoReceitasDespesasService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class BalancoReceitasDespesas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Balanço Receitas vs Despesas';

    protected static ?string $title = 'Relatório — Balanço de Receitas vs Despesas';

    protected static string $view = 'filament.pages.relatorios.balanco-receitas-despesas';

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']) ?? false;
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
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        return BalancoReceitasDespesasService::calcular($this->ano, $centroId);
    }
}
