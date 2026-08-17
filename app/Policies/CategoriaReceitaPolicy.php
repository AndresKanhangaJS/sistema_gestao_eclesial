<?php

namespace App\Policies;

use App\Models\CategoriaReceita;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * administrador_paroquial e tesoureiro_paroquial: CRUD dentro da propria
 * paroquia.
 * tesoureiro_centro: sem acesso a gestao (so usa categorias existentes ao
 * lancar receitas no MovimentoResource, o que nao passa por esta Policy).
 * consultor: so leitura, global.
 *
 * Espelha CategoriaDespesaPolicy — mesma logica, so o model muda.
 */
class CategoriaReceitaPolicy
{
    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, 'consultor']);
    }

    public function view(User $user, CategoriaReceita $categoriaReceita): bool
    {
        if ($user->hasRole('consultor')) {
            return true;
        }

        return $user->hasRole(self::GESTORES_PAROQUIA) && $categoriaReceita->paroquia_id === $user->paroquia_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function update(User $user, CategoriaReceita $categoriaReceita): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $categoriaReceita->paroquia_id === $user->paroquia_id;
    }

    /**
     * Nunca apagar uma categoria com receitas ja lancadas (protege a
     * integridade do relatorio de Demonstrativo de Arrecadacao).
     */
    public function delete(User $user, CategoriaReceita $categoriaReceita): bool
    {
        if (! $user->hasRole(self::GESTORES_PAROQUIA) || $categoriaReceita->paroquia_id !== $user->paroquia_id) {
            return false;
        }

        return $categoriaReceita->movimentos()->count() === 0;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function restore(User $user, CategoriaReceita $categoriaReceita): bool
    {
        return false;
    }

    public function forceDelete(User $user, CategoriaReceita $categoriaReceita): bool
    {
        return false;
    }
}
