<?php

namespace App\Policies;

use App\Enums\StatusConciliacao;
use App\Models\Movimento;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * tesoureiro_paroquial: CRUD dentro da paroquia + conciliacao (aprovar/rejeitar).
 * tesoureiro_centro: CRUD so no seu centro, sem conciliacao (exclusiva do paroquial).
 * consultor: so leitura, global.
 * delete/forceDelete: nunca (CLAUDE.md: nunca DELETE fisico — usar estornos).
 */
class MovimentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']);
    }

    public function view(User $user, Movimento $movimento): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole('tesoureiro_paroquial')) {
            return $movimento->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $movimento->centro_id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tesoureiro_paroquial', 'tesoureiro_centro']);
    }

    public function update(User $user, Movimento $movimento): bool
    {
        if ($movimento->status_conciliacao !== StatusConciliacao::Pendente) {
            return false;
        }

        if ($user->hasRole('tesoureiro_paroquial')) {
            return $movimento->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $movimento->centro_id === $user->centro_id;
        }

        return false;
    }

    /**
     * Conciliacao bancaria: exclusiva do tesoureiro_paroquial (CLAUDE.md).
     */
    public function aprovar(User $user, Movimento $movimento): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $movimento->paroquia_id === $user->paroquia_id;
    }

    public function rejeitar(User $user, Movimento $movimento): bool
    {
        return $this->aprovar($user, $movimento);
    }

    public function delete(User $user, Movimento $movimento): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Movimento $movimento): bool
    {
        return false;
    }

    public function forceDelete(User $user, Movimento $movimento): bool
    {
        return false;
    }
}
