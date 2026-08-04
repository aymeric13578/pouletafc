<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Folio\Folio;

class FolioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Folio::path(resource_path('views/pages'))->middleware([
            // "auth" seul laissait entrer n'importe quel compte connecté, clients
            // de la boutique compris. EnsureUserIsStaff limite aux rôles internes.
            'dashboard/*' => ['auth', \App\Http\Middleware\EnsureUserIsStaff::class],
            // Espace marchand : réservé aux comptes rattachés à une boutique, qui
            // est résolue une fois pour toutes par le middleware.
            'merchand/*' => ['auth', \App\Http\Middleware\EnsureUserHasShop::class],
        ]);
    }
}
