<?php

namespace App\Policies;

use App\Models\Turma;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * coordenador_catequese_paroquia: CRUD (incl. soft-delete) em toda a paroquia.
 * coordenador_catequese_centro: CRUD, mas apenas do seu proprio centro.
 * secretario_catequese e tesoureiro_catequese: so leitura, do seu centro —
 * colocam catequizandos em turmas via Inscricao/InscricaoTurma, mas nao
 * editam os atributos da propria turma.
 */
class TurmaPolicy
{
    private const GESTORES_PAROQUIA = ['coordenador_catequese_paroquia'];

    private const GESTORES_CENTRO = ['coordenador_catequese_centro'];

    private const LEITORES_CENTRO = ['secretario_catequese', 'tesoureiro_catequese'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO, ...self::LEITORES_CENTRO]);
    }

    public function view(User $user, Turma $turma): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $turma->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole([...self::GESTORES_CENTRO, ...self::LEITORES_CENTRO])) {
            return $turma->centro_id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function update(User $user, Turma $turma): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $turma->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $turma->centro_id === $user->centro_id;
        }

        return false;
    }

    public function delete(User $user, Turma $turma): bool
    {
        return $this->update($user, $turma);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function restore(User $user, Turma $turma): bool
    {
        return $this->update($user, $turma);
    }

    public function forceDelete(User $user, Turma $turma): bool
    {
        return false;
    }
}
