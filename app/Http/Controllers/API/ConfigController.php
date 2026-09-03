<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\ConfigurationMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuration distante d'une application mobile (voir ConfigurationMobile).
 *
 * Publique, **sans jeton** : rien ici n'est propre à un utilisateur, et les
 * mêmes valeurs sont déjà lisibles par getAppVersion/getParameters. Limitée
 * par throttle dans routes/api.php.
 */
class ConfigController extends Controller
{
    public function __invoke(Request $request, ConfigurationMobile $configuration): JsonResponse
    {
        $valide = $request->validate([
            'app' => ['nullable', 'in:' . implode(',', ConfigurationMobile::APPS)],
        ]);

        return response()->json([
            'response' => 200,
            'data' => $configuration->pour($valide['app'] ?? 'client'),
        ]);
    }
}
