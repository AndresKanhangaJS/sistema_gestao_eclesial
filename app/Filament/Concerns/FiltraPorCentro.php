<?php

namespace App\Filament\Concerns;

use App\Models\Centro;
use Illuminate\Support\Facades\Auth;

/**
 * Selector de Centro (com "Todos os centros") partilhado pelos relatorios
 * financeiros que so aceitam UM centro_id de cada vez (ao contrario da
 * FiltraMatrizDizimos, que agrega varios centros em simultaneo) —
 * Demonstrativo de Arrecadacao, Demonstrativo de Despesas, Balanco
 * Receitas vs Despesas, Fieis por Situacao.
 *
 * tesoureiro_centro e coordenador_centro nunca escolhem centro — ficam
 * sempre presos ao seu, sem selector. Os restantes papeis (admin_geral,
 * administrador_paroquial, tesoureiro_paroquial, consultor) veem por
 * defeito "Todos os centros" (centroId nulo) e podem restringir a um
 * centro especifico.
 */
trait FiltraPorCentro
{
    private const PAPEIS_CENTRO = ['tesoureiro_centro', 'coordenador_centro'];

    public ?int $centroId = null;

    protected function inicializarFiltroCentro(): void
    {
        $user = Auth::user();

        $this->centroId = $user->hasRole(self::PAPEIS_CENTRO) ? $user->centro_id : null;
    }

    public function getCentrosDisponiveis(): array
    {
        $user = Auth::user();

        if ($user->hasRole(self::PAPEIS_CENTRO)) {
            return Centro::where('id', $user->centro_id)->pluck('nome', 'id')->all();
        }

        return Centro::orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function mostrarFiltroCentro(): bool
    {
        return ! (Auth::user()?->hasRole(self::PAPEIS_CENTRO) ?? false);
    }

    /**
     * centroId e propriedade publica Livewire — adulteravel no cliente. Um
     * valor fora de getCentrosDisponiveis() (ex.: centro de outra paroquia)
     * e ignorado, cai-se para "Todos" dentro do que o utilizador pode ver.
     *
     * Publico (ao contrario do equivalente em FiltraMatrizDizimos) porque a
     * view tambem precisa do valor ja validado para o passar aos widgets de
     * grafico incorporados via @livewire — nunca $this->centroId em bruto.
     */
    public function centroIdParaConsulta(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole(self::PAPEIS_CENTRO)) {
            return $user->centro_id;
        }

        if ($this->centroId && array_key_exists($this->centroId, $this->getCentrosDisponiveis())) {
            return $this->centroId;
        }

        return null;
    }
}
