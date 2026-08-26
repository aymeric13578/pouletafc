<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\MobileAppService;
use Illuminate\Http\JsonResponse;

/**
 * Ce que l'app cliente interroge à l'ouverture pour savoir si une mise à
 * jour est disponible.
 *
 * version_code est le seul champ qui compte pour la comparaison — voir
 * config/mobile_app.php, incrémenté manuellement à chaque nouvel APK mis en
 * ligne. download_url pointe vers le Play Store dès qu'il est configuré
 * (MOBILE_APP_PLAY_STORE_URL), et seulement vers l'APK en téléchargement
 * direct tant qu'il ne l'est pas : un utilisateur ne doit jamais se voir
 * proposer de réinstaller l'APK brut par-dessus une installation Play
 * Store, d'une part parce qu'Android la refuse (signatures différentes),
 * d'autre part parce que le Play Store gère déjà ses propres mises à jour
 * indépendamment de ce popup.
 */
class AppVersionController extends Controller
{
    public function __invoke(MobileAppService $app): JsonResponse
    {
        return response()->json([
            'response' => 200,
            'data' => [
                'version_code' => config('mobile_app.android.version_code'),
                'version_name' => config('mobile_app.android.version'),
                'download_url' => $app->playStoreUrl() ?: route('shop.app.android.apk'),
            ],
        ]);
    }
}
