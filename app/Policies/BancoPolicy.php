<?php

namespace App\Policies;

use App\Models\Banco;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD (soft-delete) dentro
 * da propria paroquia — conciliacao bancaria e responsabilidade explicita
 * destes dois papeis (CLAUDE.md).
 * tesoureiro_centro: sem acesso (so usa bancos existentes ao lancar
 * movimentos, o que nao passa por esta Policy).
 * consultor: so leitura, global.
 */
class BancoPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, 'consultor']);
    }

    public function view(User $user, Banco $banco): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        return $user->hasRole(self::GESTORES_PAROQUIA) && $banco->paroquia_id === $user->paroquia_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function update(User $user, Banco $banco): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $banco->paroquia_id === $user->paroquia_id;
    }

    /**
     * Nunca apagar um banco com movimentos ja lancados (rastreabilidade
     * bancaria — Modulo 7).
     */
    public function delete(User $user, Banco $banco): bool
    {
        if (! $user->hasRole(self::GESTORES_PAROQUIA) || $banco->paroquia_id !== $user->paroquia_id) {
            return false;
        }

        return $banco->movimentos()->count() === 0;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function restore(User $user, Banco $banco): bool
    {
        return false;
    }

    public function forceDelete(User $user, Banco $banco): bool
    {
        return false;
    }
}
