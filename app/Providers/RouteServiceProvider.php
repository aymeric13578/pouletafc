<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Relevé de 60 à 100/min (2026-08-29) : un seul appareil actif sur
        // plouletafcapp cumule plusieurs sondages simultanés (suivi
        // commande/agent 3s, suivi livraison 15s, historique 15s...) et
        // dépassait facilement 60/min à lui seul, provoquant des 429 sur des
        // écrans sans rapport (produits, boutiques). Clé par IP faute
        // d'authentification sur l'API v1.0 (CLAUDE.md règle 8).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
