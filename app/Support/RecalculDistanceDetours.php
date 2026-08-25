<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\Parameter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Distance et prix d'une course Clando quand un ou plusieurs "détours" ont
 * été ajoutés — un détour est un lieu choisi à l'avance (pas la position
 * actuelle du chauffeur, voir la distinction avec un arrêt d'attente dans
 * SurchargeArrets), qui doit être traversé avant la destination finale.
 *
 * Le point de départ, chaque détour (dans l'ordre où ils ont été ajoutés)
 * puis la destination finale forment les points de passage envoyés à OSRM,
 * exactement comme le fait déjà le calcul d'itinéraire côté client
 * (clando.dart) — sauf qu'ici c'est le serveur qui refait le calcul pour
 * que le prix facturé ne dépende jamais de ce que l'app cliente a bien
 * voulu envoyer.
 */
class RecalculDistanceDetours
{
    /**
     * Recalcule `distance` et `base_price` à partir de tous les détours
     * actuels de la course. Sans effet si la course n'a aucun détour
     * (seulement des arrêts d'attente, qui ne changent pas la route) ou si
     * OSRM est injoignable — dans ce dernier cas, la distance/le prix
     * précédents restent en place plutôt que d'écraser une valeur correcte
     * par une absente.
     */
    public static function recalculer(Clando $clando): void
    {
        $detours = $clando->stops()->where('type', 'detour')->orderBy('id')->get();

        if ($detours->isEmpty()) {
            return;
        }

        $points = [];
        $points[] = "{$clando->lonMyPosition},{$clando->latMyPosition}";
        foreach ($detours as $detour) {
            $points[] = "{$detour->lon},{$detour->lat}";
        }
        $points[] = "{$clando->lonDestination},{$clando->latDestination}";

        $url = 'http://router.project-osrm.org/route/v1/driving/'
            . implode(';', $points)
            . '?overview=false';

        try {
            $response = Http::timeout(10)->get($url);
            $data = $response->json();

            if (($data['code'] ?? null) !== 'Ok' || ! isset($data['routes'][0]['distance'])) {
                Log::warning('RecalculDistanceDetours: réponse OSRM inattendue', ['ref' => $clando->ref, 'data' => $data]);

                return;
            }

            $distanceKm = $data['routes'][0]['distance'] / 1000;

            $parametres = Parameter::where('status', 'Success')->first();
            $prixKm = (float) ($parametres->clando_kilometer ?? 250);
            $prixMin = (float) ($parametres->min_price_clando ?? 500);

            $clando->distance = $distanceKm;
            $clando->base_price = max($distanceKm * $prixKm, $prixMin);
            $clando->save();
        } catch (\Throwable $e) {
            Log::warning('RecalculDistanceDetours: OSRM injoignable - ' . $e->getMessage(), ['ref' => $clando->ref]);
        }
    }
}
