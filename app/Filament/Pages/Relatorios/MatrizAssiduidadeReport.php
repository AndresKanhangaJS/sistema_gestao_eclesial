<?php

namespace App\Filament\Pages\Relatorios;

use App\Filament\Concerns\FiltraMatrizDizimos;
use App\Services\MatrizDizimosService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class MatrizAssiduidadeReport extends Page
{
    use FiltraMatrizDizimos;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Matriz de Assiduidade';

    protected static ?string $title = 'Relatório — Matriz de Assiduidade do Dízimo';

    protected static string $view = 'filament.pages.relatorios.matriz-assiduidade';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']) ?? false;
    }

    public function mount(): void
    {
        $this->inicializarFiltros();
    }

    #[Computed]
    public function linhas(): array
    {
        return $this->filtrarPorNome(
            MatrizDizimosService::calcular($this->centrosParaConsulta(), $this->ano)
        );
    }
}
