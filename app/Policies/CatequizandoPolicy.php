<?php

namespace App\Policies;

use App\Models\Catequizando;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * coordenador_catequese_paroquia: CRUD (incl. soft-delete) em toda a paroquia.
 * coordenador_catequese_centro e secretario_catequese: CRUD, mas apenas do
 * seu proprio centro.
 * tesoureiro_catequese: so leitura, do seu proprio centro (precisa de ver o
 * catequizando para emitir cobrancas, mas nao gere o registo).
 */
class CatequizandoPolicy
{
    private const GESTORES_PAROQUIA = ['coordenador_catequese_paroquia'];

    private const GESTORES_CENTRO = ['coordenador_catequese_centro', 'secretario_catequese'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO, 'tesoureiro_catequese']);
    }

    public function view(User $user, Catequizando $catequizando): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $catequizando->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole([...self::GESTORES_CENTRO, 'tesoureiro_catequese'])) {
            return $catequizando->centro_id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function update(User $user, Catequizando $catequizando): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $catequizando->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $catequizando->centro_id === $user->centro_id;
        }

        return false;
    }

    public function delete(User $user, Catequizando $catequizando): bool
    {
        return $this->update($user, $catequizando);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function restore(User $user, Catequizando $catequizando): bool
    {
        return $this->update($user, $catequizando);
    }

    public function forceDelete(User $user, Catequizando $catequizando): bool
    {
        return false;
    }
}
