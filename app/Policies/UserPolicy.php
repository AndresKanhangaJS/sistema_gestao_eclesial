<?php

namespace App\Policies;

use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial gere utilizadores da sua propria paroquia, mas so
 * os que tem papel tesoureiro_paroquial ou tesoureiro_centro — nunca
 * admin_geral, consultor, ou outro administrador_paroquial (mesmo que
 * estivesse, em teoria, na mesma paroquia). A lista de papeis atribuiveis
 * na criacao/edicao vive em UserResource::papeisAtribuiveis()/papelPermitido().
 * Nenhum outro papel tem qualquer acesso aqui.
 */
class UserPolicy
{
    private const PAPEIS_GERIVEIS = ['tesoureiro_paroquial', 'tesoureiro_centro'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrador_paroquial');
    }

    public function view(User $user, User $model): bool
    {
        return $this->podeGerir($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrador_paroquial');
    }

    public function update(User $user, User $model): bool
    {
        return $this->podeGerir($user, $model);
    }

    private function podeGerir(User $user, User $model): bool
    {
        return $user->hasRole('administrador_paroquial')
            && $model->paroquia_id === $user->paroquia_id
            && $model->hasAnyRole(self::PAPEIS_GERIVEIS);
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
