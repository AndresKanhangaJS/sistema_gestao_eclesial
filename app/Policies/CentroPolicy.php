<?php

namespace App\Policies;

use App\Models\Centro;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD (sem delete) dentro
 * da sua propria paroquia — mesmo alcance, so o administrador_paroquial
 * acumula tambem a gestao de utilizadores (UserPolicy).
 * tesoureiro_centro: so leitura, e apenas do seu proprio centro.
 * consultor: so leitura, global.
 */
class CentroPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Centro $centro): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $centro->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $centro->id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function update(User $user, Centro $centro): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $centro->paroquia_id === $user->paroquia_id;
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
