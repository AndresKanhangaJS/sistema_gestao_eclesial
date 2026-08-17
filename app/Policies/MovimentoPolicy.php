<?php

namespace App\Policies;

use App\Enums\StatusConciliacao;
use App\Models\Movimento;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD dentro da paroquia +
 * conciliacao (aprovar/rejeitar) — mesmo alcance financeiro.
 * tesoureiro_centro e coordenador_centro: CRUD so no seu centro, sem
 * conciliacao (exclusiva dos dois papeis paroquiais acima) — mesmo alcance
 * financeiro entre os dois, coordenador_centro acumula ainda gestao de Fieis
 * (FielPolicy).
 * secretario_centro: nenhum acesso a Movimentos.
 * consultor: so leitura, global.
 * delete/forceDelete: nunca (CLAUDE.md: nunca DELETE fisico — usar estornos).
 */
class MovimentoPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    private const GESTORES_CENTRO = ['tesoureiro_centro', 'coordenador_centro'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO, 'consultor']);
    }

    public function view(User $user, Movimento $movimento): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $movimento->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $movimento->centro_id === $user->centro_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::GESTORES_CENTRO]);
    }

    public function update(User $user, Movimento $movimento): bool
    {
        if ($movimento->status_conciliacao !== StatusConciliacao::Pendente) {
            return false;
        }

        if ($user->hasRole(self::GESTORES_PAROQUIA)) {
            return $movimento->paroquia_id === $user->paroquia_id;
        }

        if ($user->hasRole(self::GESTORES_CENTRO)) {
            return $movimento->centro_id === $user->centro_id;
        }

        return false;
    }

    /**
     * Conciliacao bancaria: exclusiva do administrador_paroquial e do
     * tesoureiro_paroquial (CLAUDE.md).
     */
    public function aprovar(User $user, Movimento $movimento): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $movimento->paroquia_id === $user->paroquia_id;
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
