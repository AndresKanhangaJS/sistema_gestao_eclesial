<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe as queries à paroquia do utilizador autenticado.
 *
 * Não filtra quando não há utilizador autenticado (ex: comandos artisan,
 * seeders, filas) nem enquanto a tabela users não tiver a coluna paroquia_id.
 */
class ParoquiaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $paroquiaId = Auth::check() ? Auth::user()->paroquia_id ?? null : null;

        if ($paroquiaId !== null) {
            $builder->where($model->getTable() . '.paroquia_id', $paroquiaId);
        }
    }
}
