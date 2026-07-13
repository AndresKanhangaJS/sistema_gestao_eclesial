<?php

namespace App\Policies;

use App\Models\Fiel;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD (incl. soft-delete)
 * dentro da sua propria paroquia.
 * tesoureiro_centro: so leitura, e apenas dos fieis vinculados ao seu centro.
 * consultor: so leitura, global.
 */
class FielPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Fiel $fiel): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $fiel->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $fiel->centros()->wherePivotNull('data_fim')->where('centros.id', $user->centro_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function update(User $user, Fiel $fiel): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function delete(User $user, Fiel $fiel): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function restore(User $user, Fiel $fiel): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $fiel->paroquia_id === $user->paroquia_id;
    }

    public function forceDelete(User $user, Fiel $fiel): bool
    {
        return false;
    }
}
