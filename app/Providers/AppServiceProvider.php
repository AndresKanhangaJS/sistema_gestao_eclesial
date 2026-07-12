<?php

namespace App\Providers;

use App\Models\Movimento;
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
    }
}
