<?php

namespace App\Policies;

use App\Models\Fiel;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD (incl. soft-delete)
 * dentro da sua propria paroquia.
 * tesoureiro_centro: so leitura, e apenas dos fieis vinculados ao seu centro.
 * coordenador_centro e secretario_centro: CRUD (incl. soft-delete) completo,
 * mas restrito aos fieis vinculados ao seu proprio centro — ao contrario do
 * tesoureiro_centro, que nunca gere Fieis.
 * consultor: so leitura, global.
 */
class FielPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    private const GESTORES_CENTRO = ['coordenador_centro', 'secretario_centro'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO, 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Fiel $fiel): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $fiel->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole([...self::GESTORES_CENTRO, 'tesoureiro_centro'])) {
            return $fiel->centros()->wherePivotNull('data_fim')->where('centros.id', $user->centro_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function update(User $user, Fiel $fiel): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $fiel->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $fiel->centros()->wherePivotNull('data_fim')->where('centros.id', $user->centro_id)->exists();
        }

        return false;
    }

    public function delete(User $user, Fiel $fiel): bool
    {
        return $this->update($user, $fiel);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function restore(User $user, Fiel $fiel): bool
    {
        return $this->update($user, $fiel);
    }

    public function forceDelete(User $user, Fiel $fiel): bool
    {
        return false;
    }
}
