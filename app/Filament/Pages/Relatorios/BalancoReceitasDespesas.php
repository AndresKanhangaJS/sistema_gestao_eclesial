<?php

namespace App\Filament\Pages\Relatorios;

use App\Filament\Concerns\FiltraPorCentro;
use App\Services\BalancoReceitasDespesasService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class BalancoReceitasDespesas extends Page
{
    use FiltraPorCentro;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Balanço Receitas vs Despesas';

    protected static ?string $title = 'Balanço de Receitas vs Despesas';

    protected static string $view = 'filament.pages.relatorios.balanco-receitas-despesas';

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
        return BalancoReceitasDespesasService::calcular($this->ano, $this->centroIdParaConsulta());
    }
}
