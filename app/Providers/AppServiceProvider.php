<?php

namespace App\Providers;

use App\Models\Banco;
use App\Models\CategoriaDespesa;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\User;
use App\Observers\ForcaParoquiaUtilizadorObserver;
use App\Observers\MovimentoObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // admin_geral tem acesso total ao sistema (CLAUDE.md), sem depender
        // de permissions individuais por Resource.
        Gate::before(fn ($user, string $ability) => $user->hasRole('admin_geral') ? true : null);

        Movimento::observe(MovimentoObserver::class);

        Centro::observe(ForcaParoquiaUtilizadorObserver::class);
        Fiel::observe(ForcaParoquiaUtilizadorObserver::class);
        CategoriaDespesa::observe(ForcaParoquiaUtilizadorObserver::class);
        Banco::observe(ForcaParoquiaUtilizadorObserver::class);
        // administrador_paroquial cria utilizadores (UserResource) presos a
        // paroquia_id — mesma protecao contra adulteracao do cliente.
        User::observe(ForcaParoquiaUtilizadorObserver::class);
    }
}
