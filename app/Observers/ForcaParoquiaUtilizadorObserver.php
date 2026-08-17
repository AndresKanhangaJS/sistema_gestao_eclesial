<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Fecha o isolamento multi-tenant no lado do servidor: os formularios Filament
 * escondem o campo paroquia_id para quem nao e admin_geral, mas ->visible(false)
 * nao impede a adulteracao do estado Livewire no cliente (ex.: consola do
 * browser). Este observer ignora sempre o valor submetido e fixa paroquia_id
 * a partir do utilizador autenticado, excepto para admin_geral (unico papel
 * que pode escolher a paroquia livremente).
 *
 * Aplicado a Centro, Fiel, CategoriaDespesa, CategoriaReceita, Banco e User
 * (ver AppServiceProvider).
 */
class ForcaParoquiaUtilizadorObserver
{
    public function saving(Model $model): void
    {
        $user = Auth::user();

        if (! $user || $user->hasRole('admin_geral')) {
            return;
        }

        if ($user->paroquia_id !== null) {
            $model->paroquia_id = $user->paroquia_id;
        }

        // coordenador_centro so gere utilizadores do seu proprio centro
        // (UserPolicy/UserResource::papeisAtribuiveis() ja so lhe dao papeis
        // presos a centro) — mesmo reforco, agora para centro_id, e so faz
        // sentido para o model User.
        if ($model instanceof User && $user->hasRole('coordenador_centro')) {
            $model->centro_id = $user->centro_id;
        }
    }
}
