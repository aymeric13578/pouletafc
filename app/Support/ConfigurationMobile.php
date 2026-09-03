<?php

namespace App\Support;

use App\Services\MobileAppService;
use Carbon\CarbonImmutable;

/**
 * Ce qu'une application mobile lit au démarrage (/api/v2/config) : version
 * disponible et minimale, point de retrait, contact, fonctionnalités
 * activables. Agrège des sources déjà existantes — ne crée aucune donnée.
 */
class ConfigurationMobile
{
    public const APPS = ['client', 'agent'];

    public function __construct(private MobileAppService $app, private PointDeLivraison $pointDeLivraison)
    {
    }

    public function pour(string $app): array
    {
        return [
            'app' => $app,
            'genere_a' => CarbonImmutable::now()->toIso8601String(),
            'version' => $this->version($app),
            'point_de_retrait' => $this->pointDeLivraison->pointDeRetrait(),
            'contact' => [
                'telephone' => config('mobile_app.contact.telephone') ?: null,
                'whatsapp' => config('mobile_app.contact.whatsapp') ?: null,
            ],
            'fonctionnalites' => array_map(
                fn ($actif) => (bool) $actif,
                (array) config('mobile_app.fonctionnalites', []),
            ),
        ];
    }

    /** Mêmes valeurs que AppVersionController, qui reste servi aux anciens builds. */
    private function version(string $app): array
    {
        if ($app === 'agent') {
            return [
                'code' => (int) config('mobile_app.agent.version_code', 0),
                'min_code' => (int) config('mobile_app.agent.min_version_code', 0),
                'nom' => config('mobile_app.agent.version'),
                'download_url' => route('app.agent.apk'),
            ];
        }

        return [
            'code' => (int) config('mobile_app.android.version_code', 0),
            'min_code' => (int) config('mobile_app.android.min_version_code', 0),
            'nom' => config('mobile_app.android.version'),
            'download_url' => $this->app->playStoreUrl() ?: route('shop.app.android.apk'),
        ];
    }
}
