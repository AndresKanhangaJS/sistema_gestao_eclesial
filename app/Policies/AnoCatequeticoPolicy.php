<?php

namespace App\Policies;

use App\Models\AnoCatequetico;
use App\Models\User;

/**
 * Tabela partilhada entre todas as paroquias (programa oficial da
 * Arquidiocese, docs/modulos/catequese.md seccao 3) — sem paroquia_id.
 * admin_geral tem acesso total via Gate::before (AppServiceProvider) e e o
 * unico que pode criar/editar. Os restantes papeis da Catequese so leem,
 * para poderem escolher o ano catequetico ao montar turmas/inscricoes.
 */
class AnoCatequeticoPolicy
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

    public function view(User $user, AnoCatequetico $anoCatequetico): bool
    {
        return $user->hasRole(self::PAPEIS_CATEQUESE);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AnoCatequetico $anoCatequetico): bool
    {
        return false;
    }

    public function delete(User $user, AnoCatequetico $anoCatequetico): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AnoCatequetico $anoCatequetico): bool
    {
        return false;
    }

    public function forceDelete(User $user, AnoCatequetico $anoCatequetico): bool
    {
        return false;
    }
}
