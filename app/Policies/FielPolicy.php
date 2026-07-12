<?php

namespace App\Policies;

use App\Models\Fiel;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * tesoureiro_paroquial: CRUD (incl. soft-delete) dentro da sua propria paroquia.
 * tesoureiro_centro: so leitura, e apenas dos fieis vinculados ao seu centro.
 * consultor: so leitura, global.
 */
class FielPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Fiel $fiel): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole('tesoureiro_paroquial')) {
            return $fiel->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $fiel->centros()->wherePivotNull('data_fim')->where('centros.id', $user->centro_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('tesoureiro_paroquial');
    }

    public function update(User $user, Fiel $fiel): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function delete(User $user, Fiel $fiel): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('tesoureiro_paroquial');
    }

    public function restore(User $user, Fiel $fiel): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function forceDelete(User $user, Fiel $fiel): bool
    {
        return false;
    }
}
