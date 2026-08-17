<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que utilizadores com papel restrito a uma paroquia
 * (administrador_paroquial, tesoureiro_paroquial, tesoureiro_centro,
 * coordenador_centro, secretario_centro) têm mesmo um paroquia_id atribuído
 * antes de operar no sistema.
 * Os 3 papeis de centro (tesoureiro_centro, coordenador_centro,
 * secretario_centro) têm de ter também um centro_id (CLAUDE.md: "apenas o
 * seu centro").
 * admin_geral e consultor são papeis globais e não são afectados.
 */
class EnsureParoquiaScope
{
    private const PAPEIS_CENTRO = ['tesoureiro_centro', 'coordenador_centro', 'secretario_centro'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exigeParoquia = $user && $user->hasRole(['administrador_paroquial', 'tesoureiro_paroquial', ...self::PAPEIS_CENTRO]);

        if ($exigeParoquia && $user->paroquia_id === null) {
            abort(403, 'Utilizador sem paróquia atribuída.');
        }

        if ($user && $user->hasRole(self::PAPEIS_CENTRO) && $user->centro_id === null) {
            abort(403, 'Utilizador sem centro atribuído.');
        }

        return $next($request);
    }
}
