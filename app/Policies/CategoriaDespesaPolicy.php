<?php

namespace App\Policies;

use App\Models\CategoriaDespesa;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * tesoureiro_paroquial: CRUD dentro da propria paroquia.
 * tesoureiro_centro: sem acesso a gestao (so usa categorias existentes ao
 * lancar despesas no MovimentoResource, o que nao passa por esta Policy).
 * consultor: so leitura, global.
 */
class CategoriaDespesaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['tesoureiro_paroquial', 'consultor']);
    }

    public function view(User $user, CategoriaDespesa $categoriaDespesa): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        return $user->hasRole('tesoureiro_paroquial') && $categoriaDespesa->paroquia_id === $user->paroquia_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('tesoureiro_paroquial');
    }

    public function update(User $user, CategoriaDespesa $categoriaDespesa): bool
    {
        return $user->hasRole('tesoureiro_paroquial') && $categoriaDespesa->paroquia_id === $user->paroquia_id;
    }

    /**
     * Nunca apagar uma categoria com despesas ja lancadas (protege a
     * integridade do relatorio de Balanco de Receitas vs Despesas).
     */
    public function delete(User $user, CategoriaDespesa $categoriaDespesa): bool
    {
        if (! $user->hasRole('tesoureiro_paroquial') || $categoriaDespesa->paroquia_id !== $user->paroquia_id) {
            return false;
        }

        return $categoriaDespesa->movimentos()->count() === 0;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('tesoureiro_paroquial');
    }

    public function restore(User $user, CategoriaDespesa $categoriaDespesa): bool
    {
        return false;
    }

    public function forceDelete(User $user, CategoriaDespesa $categoriaDespesa): bool
    {
        return false;
    }
}
