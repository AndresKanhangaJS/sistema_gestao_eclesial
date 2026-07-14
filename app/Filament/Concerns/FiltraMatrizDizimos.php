<?php

namespace App\Filament\Concerns;

use App\Models\Centro;
use App\Models\Fiel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Logica de filtragem partilhada entre a Matriz de Dizimos (Modulo 5) e o
 * Relatorio de Matriz de Assiduidade (Modulo 7): resolucao de que centros
 * consultar consoante o papel do utilizador, e o filtro por nome do fiel.
 *
 * tesoureiro_centro nunca escolhe centro — fica sempre preso ao seu, sem
 * selector. Os restantes papeis (admin_geral, administrador_paroquial,
 * tesoureiro_paroquial) veem por defeito "Todos os centros" (centroId nulo)
 * e podem restringir a um centro especifico.
 */
trait FiltraMatrizDizimos
{
    public ?int $centroId = null;

    public ?string $nomeFiel = null;

    public int $ano;

    protected function inicializarFiltros(): void
    {
        $this->ano = (int) now()->year;

        $user = Auth::user();

        $this->centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
    }

    public function getCentrosDisponiveis(): array
    {
        $user = Auth::user();

        if ($user->hasRole('tesoureiro_centro')) {
            return Centro::where('id', $user->centro_id)->pluck('nome', 'id')->all();
        }

        return Centro::orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function mostrarFiltroCentro(): bool
    {
        return ! (Auth::user()?->hasRole('tesoureiro_centro') ?? false);
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    /**
     * centroId e propriedade publica Livewire — adulteravel no cliente. Um
     * valor fora de getCentrosDisponiveis() (ex.: centro de outra paroquia)
     * e ignorado, nunca chega ao MatrizDizimosService — cai-se para "Todos"
     * dentro do que o utilizador pode mesmo ver.
     *
     * @return array<int, int>
     */
    protected function centrosParaConsulta(): array
    {
        $user = Auth::user();

        if ($user->hasRole('tesoureiro_centro')) {
            return [$user->centro_id];
        }

        if ($this->centroId && array_key_exists($this->centroId, $this->getCentrosDisponiveis())) {
            return [$this->centroId];
        }

        return array_keys($this->getCentrosDisponiveis());
    }

    /**
     * @param  array<int, array{fiel: Fiel, meses: array<int, string>, total_pagos: int, segmento: string}>  $linhas
     * @return array<int, array{fiel: Fiel, meses: array<int, string>, total_pagos: int, segmento: string}>
     */
    protected function filtrarPorNome(array $linhas): array
    {
        if (! filled($this->nomeFiel)) {
            return $linhas;
        }

        $termo = Str::lower($this->nomeFiel);

        return array_values(array_filter(
            $linhas,
            fn (array $linha) => str_contains(Str::lower($linha['fiel']->nome), $termo)
        ));
    }
}
