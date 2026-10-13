<?php

use App\Http\Middleware\EnsureParoquiaScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'paroquia.scope' => EnsureParoquiaScope::class,
        ]);

        // Em producao o HTTPS termina num proxy (Caddy/nginx/Cloudflare) e o
        // pedido chega aqui em HTTP: sem confiar nos cabecalhos X-Forwarded-*,
        // o Laravel gera URLs http:// e o browser bloqueia os assets (ex.: o
        // grafico do dashboard) como conteudo misto. TRUSTED_PROXIES aceita
        // '*' (defeito) ou uma lista de IPs/CIDR separados por virgula.
        $proxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
