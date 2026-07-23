<?php

namespace App\Policies;

use App\Models\Inscricao;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * coordenador_catequese_paroquia: CRUD (incl. soft-delete) em toda a paroquia.
 * coordenador_catequese_centro e secretario_catequese: CRUD, mas apenas do
 * seu proprio centro — inscricoes/matriculas sao explicitamente
 * responsabilidade do secretario_catequese (docs/modulos/catequese.md
 * seccao 2).
 * tesoureiro_catequese: so leitura, do seu centro.
 *
 * Trocar de turma (InscricaoTurma) e mudar de centro nao passam por aqui —
 * sao geridos pelas policies de Turma/Catequizando e por acoes dedicadas no
 * RelationManager, nunca por update() directo do campo turma (que nem
 * existe nesta tabela — ver docs/modulos/catequese.md seccao 7).
 */
class InscricaoPolicy
{
    private const GESTORES_PAROQUIA = ['coordenador_catequese_paroquia'];

    private const GESTORES_CENTRO = ['coordenador_catequese_centro', 'secretario_catequese'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO, 'tesoureiro_catequese']);
    }

    public function view(User $user, Inscricao $inscricao): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $inscricao->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole([...self::GESTORES_CENTRO, 'tesoureiro_catequese'])) {
            return $inscricao->centro_id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function update(User $user, Inscricao $inscricao): bool
    {
        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $inscricao->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $inscricao->centro_id === $user->centro_id;
        }

        return false;
    }

    public function delete(User $user, Inscricao $inscricao): bool
    {
        return $this->update($user, $inscricao);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function restore(User $user, Inscricao $inscricao): bool
    {
        return $this->update($user, $inscricao);
    }

    public function forceDelete(User $user, Inscricao $inscricao): bool
    {
        return false;
    }
}
