<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnsureParoquiaScope;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            // Permite recolher o menu lateral no desktop (fica so com os
            // icones + a opcao seleccionada em destaque); continua
            // totalmente clicavel/navegavel nesse estado, so o texto some.
            ->sidebarCollapsibleOnDesktop()
            // Sino de notificacoes no topo do painel — sem isto, mesmo com a
            // tabela `notifications` a existir e a ser preenchida (ex.: pelo
            // ImportAction ao terminar uma importacao em segundo plano), nao
            // ha nenhuma superficie na UI a mostrar essas notificacoes ao
            // utilizador (bug reportado: "os dados persistem mas a
            // mensagem de sucesso nao aparece").
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            // BemVindoWidget (substitui o AccountWidget nativo) e os graficos
            // de arrecadacao ja vivem em app/Filament/Widgets e sao
            // apanhados por este discover — registar o AccountWidget aqui
            // tambem duplicava o card de boas-vindas.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureParoquiaScope::class,
            ]);
    }
}
