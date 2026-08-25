<?php

namespace App\Support;

use App\Models\Clando;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Majoration de prix due aux arrêts ajoutés à une course Clando en cours.
 *
 * Chaque arrêt coûte 100 F par tranche de 10 minutes écoulée depuis son
 * ajout — un arrêt de 4 minutes compte comme une tranche entière (100 F),
 * un arrêt de 11 minutes comme deux (200 F). La tranche en cours est
 * comptée tant que la course n'est pas terminée : la majoration affichée
 * pendant la course est donc une estimation qui continue de monter, et la
 * facture finale est recalculée une dernière fois à la fin de course
 * (App\Http\Controllers\API\ClandoController::terminatedCourse) pour
 * inclure la tranche en cours au moment exact de l'arrivée.
 */
class SurchargeArrets
{
    private const PRIX_PAR_TRANCHE = 100;
    private const MINUTES_PAR_TRANCHE = 10;

    /**
     * Majoration totale pour une collection d'arrêts, calculée à l'instant
     * donné (ou maintenant si omis).
     */
    public static function calculer(Collection $arrets, ?Carbon $jusqua = null): float
    {
        $jusqua ??= Carbon::now();

        return (float) $arrets->sum(function ($arret) use ($jusqua) {
            $minutes = Carbon::parse($arret->created_at)->diffInMinutes($jusqua);
            $tranches = (int) ceil(max($minutes, 0) / self::MINUTES_PAR_TRANCHE);

            return $tranches * self::PRIX_PAR_TRANCHE;
        });
    }

    /**
     * Recalcule et enregistre le prix total d'une course (base + arrêts) à
     * l'instant donné. Suppose `base_price` déjà initialisé — par le
     * contrôleur au tout premier arrêt ajouté (type 'attente'), ou par
     * App\Support\RecalculDistanceDetours (type 'detour', qui le
     * recalcule lui-même à chaque détour ajouté).
     */
    public static function recalculerPrix(Clando $clando, ?Carbon $jusqua = null): Clando
    {
        $majoration = self::calculer($clando->stops()->get(), $jusqua);
        // Repli défensif : ne devrait pas arriver (le contrôleur initialise
        // toujours base_price avant d'appeler cette méthode), mais mieux
        // vaut retomber sur le prix courant que perdre la base du calcul.
        $base = $clando->base_price ?? $clando->price;

        $clando->stops_surcharge = $majoration;
        $clando->price = $base + $majoration;
        $clando->save();

        return $clando;
    }
}
