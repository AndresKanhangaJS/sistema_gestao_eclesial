<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que utilizadores com papel restrito a uma paroquia
 * (administrador_paroquial, tesoureiro_paroquial, tesoureiro_centro) têm
 * mesmo um paroquia_id atribuído antes de operar no sistema.
 * tesoureiro_centro tem de ter também um centro_id (CLAUDE.md: "apenas o seu centro").
 * admin_geral e consultor são papeis globais e não são afectados.
 */
class EnsureParoquiaScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exigeParoquia = $user && $user->hasRole(['administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']);

        if ($exigeParoquia && $user->paroquia_id === null) {
            abort(403, 'Utilizador sem paróquia atribuída.');
        }

        if ($user && $user->hasRole('tesoureiro_centro') && $user->centro_id === null) {
            abort(403, 'Utilizador sem centro atribuído.');
        }

        return $next($request);
    }
}
