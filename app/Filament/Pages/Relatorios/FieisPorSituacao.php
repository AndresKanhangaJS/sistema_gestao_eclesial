<?php

namespace App\Filament\Pages\Relatorios;

use App\Filament\Concerns\FiltraPorCentro;
use App\Services\FieisPorSituacaoService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class FieisPorSituacao extends Page
{
    use FiltraPorCentro;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Fiéis por Situação';

    protected static ?string $title = 'Fiéis por Situação';

    protected static string $view = 'filament.pages.relatorios.fieis-por-situacao';

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
    public function linhas(): array
    {
        return FieisPorSituacaoService::calcular($this->ano, $this->centroIdParaConsulta());
    }
}
