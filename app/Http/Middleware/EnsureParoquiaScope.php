<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que utilizadores com papel restrito a uma paroquia (tesoureiro_paroquial,
 * tesoureiro_centro) têm mesmo um paroquia_id atribuído antes de operar no sistema.
 * admin_geral e consultor são papeis globais e não são afectados.
 */
class EnsureParoquiaScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exigeParoquia = $user && ($user->hasRole('tesoureiro_paroquial') || $user->hasRole('tesoureiro_centro'));

        if ($exigeParoquia && $user->paroquia_id === null) {
            abort(403, 'Utilizador sem paroquia atribuida.');
        }

        return $next($request);
    }
}
