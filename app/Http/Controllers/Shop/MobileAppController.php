<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\MobileAppService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileAppController extends Controller
{
    public function __construct(protected MobileAppService $app)
    {
    }

    /**
     * Page dédiée à l'application : téléchargement de l'APK + marche à suivre
     * pour l'installer hors store (l'utilisateur doit autoriser l'installation
     * depuis une source inconnue, ce qui mérite d'être expliqué).
     */
    public function show(): Response
    {
        return Inertia::render('Static/MobileApp', [
            'app' => $this->app->toArray(),
        ]);
    }

    /**
     * Sert l'APK CLANDO en téléchargement direct.
     *
     * Le fichier est servi par PHP plutôt que déposé dans public/ : cela évite
     * de versionner un binaire de plusieurs dizaines de Mo, et garantit le bon
     * type MIME (certains hébergements mutualisés refusent de servir un .apk
     * statique, ou le renvoient en text/plain, ce qui casse l'installation).
     */
    public function apk(): BinaryFileResponse
    {
        abort_unless($this->app->apkIsAvailable(), 404, "L'application n'est pas encore disponible au téléchargement.");

        return response()
            ->download(
                $this->app->apkPath(),
                config('mobile_app.android.apk_filename'),
                ['Content-Type' => 'application/vnd.android.package-archive']
            );
    }

    /**
     * Sert l'application agent.
     *
     * Volontairement en accès libre, comme l'application cliente : le lien est
     * partagé par l'administration à des livreurs qui n'ont pas encore de
     * compte, et qui ne pourraient donc pas s'authentifier pour la télécharger.
     * Le binaire n'expose rien : sans compte agent validé, il ne donne accès à
     * aucune donnée.
     */
    public function agentApk(): BinaryFileResponse
    {
        abort_unless(
            $this->app->agentApkIsAvailable(),
            404,
            "L'application agent n'est pas encore disponible au téléchargement."
        );

        return response()
            ->download(
                $this->app->agentApkPath(),
                config('mobile_app.agent.apk_filename'),
                ['Content-Type' => 'application/vnd.android.package-archive']
            );
    }
}
