<?php

namespace App\Policies;

use App\Models\Centro;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * tesoureiro_paroquial: CRUD (sem delete) dentro da sua propria paroquia.
 * tesoureiro_centro: so leitura, e apenas do seu proprio centro.
 * consultor: so leitura, global.
 */
class CentroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Centro $centro): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole('tesoureiro_paroquial')) {
            return $centro->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $centro->id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('tesoureiro_paroquial');
    }

    public function update(User $user, Centro $centro): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $centro->paroquia_id === $user->paroquia_id;
    }

    public function delete(User $user, Centro $centro): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Centro $centro): bool
    {
        return false;
    }

    public function forceDelete(User $user, Centro $centro): bool
    {
        return false;
    }
}
