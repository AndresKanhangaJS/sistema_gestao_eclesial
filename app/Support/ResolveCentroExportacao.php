<?php

namespace App\Support;

use App\Models\Centro;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolve qual centro usar num relatorio/exportacao, a partir do papel do
 * utilizador e do query param opcional ?centro_id= (preenchido pelo select
 * "Todos os centros" das paginas de relatorio ou do modal de exportacao da
 * Catequese). tesoureiro_centro e coordenador_centro ficam sempre presos ao
 * seu proprio centro, ignorando o parametro; os restantes podem escolher um
 * centro especifico ou "Todos" (sem valor, ou valor invalido, devolve null).
 *
 * Nunca confia no query param sem o validar contra o que o utilizador pode
 * mesmo ver — a ParoquiaScope do model Centro ja faz esse trabalho (um
 * centro de outra paroquia simplesmente nao existe na query, cai para
 * null/"Todos" em vez de rebentar ou vazar dados).
 */
class ResolveCentroExportacao
{
    private const PAPEIS_CENTRO_FINANCEIRO = ['tesoureiro_centro', 'coordenador_centro'];

    private const PAPEIS_CENTRO_CATEQUESE = ['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'];

    /**
     * @param  array<int, string>  $papeisPresos  Papeis que ficam sempre
     *                                             presos ao seu proprio centro, ignorando o query param — cada
     *                                             modulo (financeiro/catequese) tem os seus.
     */
    public static function centro(User $user, Request $request, array $papeisPresos = self::PAPEIS_CENTRO_FINANCEIRO): ?Centro
    {
        if ($user->hasRole($papeisPresos)) {
            return Centro::find($user->centro_id);
        }

        $centroId = $request->query('centro_id');

        return filled($centroId) ? Centro::find($centroId) : null;
    }

    public static function centroCatequese(User $user, Request $request): ?Centro
    {
        return self::centro($user, $request, self::PAPEIS_CENTRO_CATEQUESE);
    }
}
