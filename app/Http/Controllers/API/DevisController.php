<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tarif;
use App\Support\Distance;
use App\Support\Tarification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Devis d'une course : le prix que le serveur facturera pour un service et
 * une distance, à l'instant de l'appel.
 *
 * Première route du préfixe `v2`. Volontairement **sans jeton** : c'est un
 * calcul pur sur des tarifs déjà publics (`getParameters` les expose), sans
 * identité ni effet de bord — le recalcul qui fait foi a lieu à la création
 * de la course (Insertclando, storeDeliveryOrder). Limité par throttle dans
 * routes/api.php.
 */
class DevisController extends Controller
{
    /** Une route ne peut pas être plus courte que la ligne droite ; 5 % de marge pour les arrondis GPS. */
    private const TOLERANCE_VOL_D_OISEAU = 0.95;

    public function __invoke(Request $request, Tarification $tarification): JsonResponse
    {
        $valide = $request->validate([
            'service' => ['required', 'in:' . implode(',', array_keys(Tarif::SERVICES))],
            'distance_km' => ['required', 'numeric', 'gt:0', 'max:500'],
            'type' => ['nullable', 'in:classic,vip'],
            'lat_depart' => ['nullable', 'numeric', 'required_with:lon_depart,lat_arrivee,lon_arrivee'],
            'lon_depart' => ['nullable', 'numeric', 'required_with:lat_depart,lat_arrivee,lon_arrivee'],
            'lat_arrivee' => ['nullable', 'numeric', 'required_with:lat_depart,lon_depart,lon_arrivee'],
            'lon_arrivee' => ['nullable', 'numeric', 'required_with:lat_depart,lon_depart,lat_arrivee'],
        ]);

        $distanceKm = (float) $valide['distance_km'];

        if (isset($valide['lat_depart'], $valide['lon_depart'], $valide['lat_arrivee'], $valide['lon_arrivee'])) {
            $volDOiseauKm = Distance::metres(
                (float) $valide['lat_depart'], (float) $valide['lon_depart'],
                (float) $valide['lat_arrivee'], (float) $valide['lon_arrivee'],
            ) / 1000;

            if ($distanceKm < $volDOiseauKm * self::TOLERANCE_VOL_D_OISEAU) {
                throw ValidationException::withMessages([
                    'distance_km' => 'Distance incohérente avec les coordonnées fournies.',
                ]);
            }
        }

        $devis = $tarification->devis(
            $valide['service'],
            $distanceKm,
            ($valide['type'] ?? 'classic') === 'vip',
        );

        return response()->json(['response' => 200, 'data' => $devis->toArray()]);
    }
}
