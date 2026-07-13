<?php

namespace App\Filament\Pages\Relatorios;

use App\Models\Centro;
use App\Services\MatrizDizimosService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class MatrizAssiduidadeReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Matriz de Assiduidade';

    protected static ?string $title = 'Relatório — Matriz de Assiduidade do Dízimo';

    protected static string $view = 'filament.pages.relatorios.matriz-assiduidade';

    public ?int $centroId = null;

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']) ?? false;
    }

    public function mount(): void
    {
        $this->ano = (int) now()->year;

        $user = Auth::user();

        $this->centroId = $user->hasRole('tesoureiro_centro')
            ? $user->centro_id
            : Centro::query()->orderBy('nome')->value('id');
    }

    public function getCentrosDisponiveis(): array
    {
        $user = Auth::user();

        if ($user->hasRole('tesoureiro_centro')) {
            return Centro::where('id', $user->centro_id)->pluck('nome', 'id')->all();
        }

        return Centro::orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    #[Computed]
    public function linhas(): array
    {
        if (! $this->centroId) {
            return [];
        }

        return MatrizDizimosService::calcular($this->centroId, $this->ano);
    }
}
