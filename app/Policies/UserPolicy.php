<?php

namespace App\Policies;

use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial gere utilizadores da sua propria paroquia, mas so
 * os que tem papel tesoureiro_paroquial, tesoureiro_centro,
 * coordenador_centro, secretario_centro, coordenador_catequese_paroquia ou
 * secretario_catequese — nunca admin_geral, consultor, outro
 * administrador_paroquial, nem (por agora)
 * coordenador_catequese_centro/tesoureiro_catequese (ver
 * docs/modulos/catequese.md, pendencia de RBAC).
 * coordenador_centro (2026-08-17) gere utilizadores do seu proprio centro,
 * mas so tesoureiro_centro/secretario_centro — nunca outro coordenador_centro
 * nem papeis de catequese, que ficam fora do seu alcance.
 * A lista de papeis atribuiveis na criacao/edicao vive em
 * UserResource::papeisAtribuiveis()/papelPermitido().
 * Nenhum outro papel tem qualquer acesso aqui.
 */
class UserPolicy
{
    private const PAPEIS_GERIVEIS_ADMIN_PAROQUIAL = [
        'tesoureiro_paroquial',
        'tesoureiro_centro',
        'coordenador_centro',
        'secretario_centro',
        'coordenador_catequese_paroquia',
        'secretario_catequese',
    ];

    private const PAPEIS_GERIVEIS_COORDENADOR_CENTRO = [
        'tesoureiro_centro',
        'secretario_centro',
    ];

    private const GESTORES = ['administrador_paroquial', 'coordenador_centro'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::GESTORES);
    }

    public function view(User $user, User $model): bool
    {
        return $this->podeGerir($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES);
    }

    public function update(User $user, User $model): bool
    {
        return $this->podeGerir($user, $model);
    }

    /**
     * Redefinir a senha de outro utilizador (accao dedicada na tabela) exige
     * a mesma autorizacao que editá-lo por completo.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $this->podeGerir($user, $model);
    }

    private function podeGerir(User $user, User $model): bool
    {
        if ($user->hasRole('administrador_paroquial')) {
            return $model->paroquia_id === $user->paroquia_id
                && $model->hasAnyRole(self::PAPEIS_GERIVEIS_ADMIN_PAROQUIAL);
        }

        if ($user->hasRole('coordenador_centro')) {
            return $model->centro_id === $user->centro_id
                && $model->hasAnyRole(self::PAPEIS_GERIVEIS_COORDENADOR_CENTRO);
        }

        return false;
    }

    /**
     * Nunca apagar um utilizador pela UI: movimentos.usuario_id e
     * restrictOnDelete (integridade do historico financeiro). Sem soft
     * delete nesta tabela, um forceDelete quebraria essa FK.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
