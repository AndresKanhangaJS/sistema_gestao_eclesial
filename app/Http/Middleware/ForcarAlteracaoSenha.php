<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Utilizadores com deve_alterar_senha=true (conta nova ou senha redefinida
 * por um administrador_paroquial/coordenador_centro — ver
 * UserResource::redefinirSenhaAction() e CreateUser::afterCreate()) sao
 * sempre reencaminhados para AlterarSenhaObrigatoria antes de poderem usar
 * qualquer outra pagina do painel: a senha inicial nunca e escolhida pelo
 * proprio utilizador.
 */
class ForcarAlteracaoSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->deve_alterar_senha) {
            return $next($request);
        }

        if ($request->routeIs(['filament.admin.auth.logout', 'filament.admin.pages.alterar-senha'])) {
            return $next($request);
        }

        return redirect()->route('filament.admin.pages.alterar-senha');
    }
}
