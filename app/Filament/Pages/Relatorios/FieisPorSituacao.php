<?php

namespace App\Filament\Pages\Relatorios;

use App\Services\FieisPorSituacaoService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class FieisPorSituacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Fiéis por Situação';

    protected static ?string $title = 'Relatório — Fiéis por Situação';

    protected static string $view = 'filament.pages.relatorios.fieis-por-situacao';

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
    public function linhas(): array
    {
        $user = Auth::user();
        $centroId = $user?->hasRole('tesoureiro_centro') ? $user->centro_id : null;

        return FieisPorSituacaoService::calcular($this->ano, $centroId);
    }
}
