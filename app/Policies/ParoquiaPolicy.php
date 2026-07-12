<?php

namespace App\Policies;

use App\Models\Paroquia;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * consultor: so leitura, global. tesoureiro_paroquial/tesoureiro_centro nao
 * gerem dados da paroquia em si (apenas operam dentro dela).
 */
class ParoquiaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('consultor');
    }

    public function view(User $user, Paroquia $paroquia): bool
    {
        return $user->hasRole('consultor');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Paroquia $paroquia): bool
    {
        return false;
    }

    public function delete(User $user, Paroquia $paroquia): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Paroquia $paroquia): bool
    {
        return false;
    }

    public function forceDelete(User $user, Paroquia $paroquia): bool
    {
        return false;
    }
}
