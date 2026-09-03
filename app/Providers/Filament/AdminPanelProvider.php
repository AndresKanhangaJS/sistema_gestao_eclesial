<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnsureParoquiaScope;
use App\Http\Middleware\ForcarAlteracaoSenha;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            // Ordem explicita dos grupos da barra lateral — sem isto,
            // "Catequizandos" (grupo novo, criado so pelo Catequizando
            // Resource via getNavigationItems()) cairia no fim da lista por
            // ordem alfabetica, longe do resto da Catequese.
            ->navigationGroups([
                NavigationGroup::make('Estrutura'),
                NavigationGroup::make('Fiéis'),
                NavigationGroup::make('Financeiro'),
                NavigationGroup::make('Catequese'),
                NavigationGroup::make('Catequizandos')->collapsed(),
                NavigationGroup::make('Catequese · Configuração'),
                NavigationGroup::make('Relatórios'),
                NavigationGroup::make('Acessos'),
            ])
            // Sino de notificacoes no topo do painel — sem isto, mesmo com a
            // tabela `notifications` a existir e a ser preenchida (ex.: pelo
            // ImportAction ao terminar uma importacao em segundo plano), nao
            // ha nenhuma superficie na UI a mostrar essas notificacoes ao
            // utilizador (bug reportado: "os dados persistem mas a
            // mensagem de sucesso nao aparece").
            ->databaseNotifications()
            // Rodape "made by <AVK/>" em todo o painel (incl. login) — o hook
            // FOOTER e renderizado tanto no layout autenticado como no
            // layout "simple" das paginas de autenticacao do Filament.
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('filament.footer')->render(),
            )
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
                ForcarAlteracaoSenha::class,
            ]);
    }
}
