<?php

namespace App\Policies;

use App\Models\Sacramento;
use App\Models\User;

/**
 * Tabela partilhada entre todas as paroquias (docs/modulos/catequese.md
 * seccao 3) — sem paroquia_id. admin_geral tem acesso total via Gate::before
 * (AppServiceProvider) e e o unico que pode criar/editar. Os restantes
 * papeis da Catequese so leem, para poderem escolher o(s) sacramento(s) ao
 * montar turmas.
 */
class SacramentoPolicy
{
    private const PAPEIS_CATEQUESE = [
        'coordenador_catequese_paroquia',
        'coordenador_catequese_centro',
        'secretario_catequese',
        'tesoureiro_catequese',
    ];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::PAPEIS_CATEQUESE);
    }

    public function view(User $user, Sacramento $sacramento): bool
    {
        return $user->hasRole(self::PAPEIS_CATEQUESE);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Sacramento $sacramento): bool
    {
        return false;
    }

    public function delete(User $user, Sacramento $sacramento): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Sacramento $sacramento): bool
    {
        return false;
    }

    public function forceDelete(User $user, Sacramento $sacramento): bool
    {
        return false;
    }
}
