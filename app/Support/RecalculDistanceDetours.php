<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\Tarif;
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
 *
 * Le prix de base passe par App\Support\Tarification, le même moteur que le
 * prix initial de la course : arrondi à 50, majoration VIP et grille
 * horaire compris — le calcul direct sur `parameters` qui précédait faisait
 * perdre sa majoration à un client VIP dès le premier détour.
 */
class RecalculDistanceDetours
{
    /**
     * Recalcule `distance` et `base_price` à partir de tous les détours
     * actuels de la course. Sans effet si la course n'a aucun détour ou si
     * OSRM est injoignable — la distance/le prix précédents restent en place
     * plutôt que d'écraser une valeur correcte par une absente.
     */
    public static function recalculer(Clando $clando): void
    {
        $detours = $clando->stops()->where('type', 'detour')->orderBy('id')->get();

        if ($detours->isEmpty()) {
            return;
        }

        $distanceKm = self::distanceParOsrm(self::pointsDePassage($clando, $detours), (string) $clando->ref);

        if ($distanceKm === null) {
            return;
        }

        self::appliquer($clando, $distanceKm)->save();
    }

    /**
     * Départ, détours dans l'ordre, destination — au format « lon,lat » d'OSRM.
     *
     * @param  iterable<\App\Models\ClandoStop>  $detours
     * @return list<string>
     */
    public static function pointsDePassage(Clando $clando, iterable $detours): array
    {
        $points = ["{$clando->lonMyPosition},{$clando->latMyPosition}"];
        foreach ($detours as $detour) {
            $points[] = "{$detour->lon},{$detour->lat}";
        }
        $points[] = "{$clando->lonDestination},{$clando->latDestination}";

        return $points;
    }

    /** Distance routière en km, ou null si OSRM ne répond pas correctement. */
    public static function distanceParOsrm(array $points, string $ref = ''): ?float
    {
        $url = 'http://router.project-osrm.org/route/v1/driving/' . implode(';', $points) . '?overview=false';

        try {
            $data = Http::timeout(10)->get($url)->json();

            if (($data['code'] ?? null) !== 'Ok' || ! isset($data['routes'][0]['distance'])) {
                Log::warning('RecalculDistanceDetours: réponse OSRM inattendue', ['ref' => $ref, 'data' => $data]);

                return null;
            }

            return $data['routes'][0]['distance'] / 1000;
        } catch (\Throwable $e) {
            Log::warning('RecalculDistanceDetours: OSRM injoignable - ' . $e->getMessage(), ['ref' => $ref]);

            return null;
        }
    }

    /**
     * Nouvelle distance et nouveau prix de base — par le même moteur que le
     * prix initial (App\Support\Tarification). Ne sauvegarde pas.
     */
    public static function appliquer(Clando $clando, float $distanceKm): Clando
    {
        $clando->distance = $distanceKm;
        $clando->base_price = app(Tarification::class)
            ->devis(Tarif::CLANDO, $distanceKm, $clando->type === 'vip')
            ->prix;

        return $clando;
    }
}
