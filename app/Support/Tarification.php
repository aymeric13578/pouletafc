<?php

namespace App\Support;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Le prix d'une course, calculé par le serveur.
 *
 * Jusqu'ici chaque application mobile calculait le prix elle-même à partir
 * de `getParameters`, puis l'envoyait au serveur qui l'enregistrait tel quel
 * — un montant modifié côté téléphone servait ensuite de base à la commission
 * de l'agent. Ce moteur est désormais la seule source : les applications
 * affichent ce qu'il renvoie (`POST /api/v2/devis`) et les créations de
 * course le rappellent (voir prixRetenu()).
 *
 * Deux générations de tarifs, comme pour les commissions (GrilleTarifaire) :
 *  - une grille par service et plage horaire → TarifPlage::prixPour() ;
 *  - sinon la ligne plate `parameters`, avec **les formules exactes des
 *    applications** (voir docs/superpowers/plans/2026-09-03-moteur-tarification-devis.md
 *    §0.2) pour qu'aucun prix affiché ne bouge le jour de la bascule.
 */
class Tarification
{
    /**
     * Valeurs codées en dur dans plouletafcapp quand getParameters n'a pas
     * répondu. Reprises telles quelles : sans `parameters`, le serveur doit
     * donner le même prix que l'application aurait affiché.
     */
    private const DEFAUTS = [
        Tarif::CLANDO => ['km' => 250, 'min' => 500, 'vip' => 50],
        Tarif::LIVRAISON => ['km' => 63, 'min' => 400],
        Tarif::COURSIER => ['km' => 200, 'min' => 500],
    ];

    public function __construct(private GrilleTarifaire $grille)
    {
    }

    public function devis(string $service, float $distanceKm, bool $vip = false): Devis
    {
        if (! array_key_exists($service, Tarif::SERVICES)) {
            throw new \InvalidArgumentException("Service inconnu : {$service}");
        }

        // Le VIP n'existe que pour le clando (Tarif::SERVICES_AVEC_VIP) ; le
        // demander ailleurs n'est pas une erreur, c'est simplement sans effet.
        $vip = $vip && in_array($service, Tarif::SERVICES_AVEC_VIP, true);
        $distanceKm = max($distanceKm, 0.0);

        $plage = $this->grille->plage($service);

        if ($plage) {
            return $this->depuisLaGrille($service, $distanceKm, $vip, $plage);
        }

        return $this->depuisLesParametres($service, $distanceKm, $vip, $this->grille->parametres());
    }

    /**
     * Le prix à enregistrer à la création d'une course.
     *
     * Dès que le client fournit une distance exploitable, le serveur calcule
     * et son prix l'emporte — le montant envoyé par le téléphone n'est plus
     * qu'un indice, journalisé s'il diffère (pour repérer les anciens builds
     * ou une manipulation). Sans distance, on garde le comportement
     * historique : prix client, plancher à 1 F, null si invalide.
     */
    public function prixRetenu(string $service, mixed $prixClient, mixed $distanceKm, bool $vip = false): ?int
    {
        $prixClientValide = is_numeric($prixClient) && (float) $prixClient >= 1 ? (int) round((float) $prixClient) : null;

        if (! is_numeric($distanceKm) || (float) $distanceKm <= 0) {
            return $prixClientValide;
        }

        $prixServeur = $this->devis($service, (float) $distanceKm, $vip)->prix;

        if ($prixClientValide !== null && $prixClientValide !== $prixServeur) {
            Log::warning('Tarification: prix client différent du prix serveur', [
                'service' => $service,
                'distance_km' => (float) $distanceKm,
                'vip' => $vip,
                'prix_client' => $prixClientValide,
                'prix_serveur' => $prixServeur,
            ]);
        }

        return $prixServeur;
    }

    private function depuisLaGrille(string $service, float $km, bool $vip, TarifPlage $plage): Devis
    {
        return new Devis(
            service: $service,
            distanceKm: $km,
            vip: $vip,
            prix: $plage->prixPour($km, $vip),
            prixClassique: $plage->prixPour($km, false),
            source: Devis::SOURCE_GRILLE,
            tarif: [
                'prix_km' => $plage->prix_km,
                'prix_min' => $plage->prix_min,
                'prix_max' => $plage->prix_max,
                'majoration_vip' => $plage->majoration_vip,
                'debut' => $plage->debutCourt(),
                'fin' => $plage->finCourte(),
            ],
            calculeA: CarbonImmutable::now(),
        );
    }

    private function depuisLesParametres(string $service, float $km, bool $vip, ?Parameter $p): Devis
    {
        $defauts = self::DEFAUTS[$service];

        // Une ligne `parameters` peut exister sans les colonnes coursier
        // (grilles enregistrées avant leur ajout) : champ par champ, on
        // retombe sur la valeur historique plutôt que sur 0.
        $valeur = fn (string $colonne, int $defaut): float => (float) ($p?->getAttribute($colonne) ?? $defaut);

        [$prixKm, $prixMin, $majorationVip] = match ($service) {
            Tarif::CLANDO => [
                $valeur('clando_kilometer', $defauts['km']),
                $valeur('min_price_clando', $defauts['min']),
                $valeur('vip_percentage', $defauts['vip']),
            ],
            Tarif::LIVRAISON => [
                $valeur('command_kilometer', $defauts['km']),
                $valeur('min_price_command', $defauts['min']),
                0.0,
            ],
            Tarif::COURSIER => [
                $valeur('coursier_kilometer', $defauts['km']),
                $valeur('min_price_coursier', $defauts['min']),
                0.0,
            ],
        };

        // Coursier : formule additive (base + km × tarif), les deux autres :
        // plancher. C'est ce que font les écrans respectifs de l'application.
        $classique = $service === Tarif::COURSIER
            ? self::arrondi50($prixMin + $km * $prixKm)
            : self::arrondi50(max($km * $prixKm, $prixMin));

        // VIP : majoration appliquée au prix classique *déjà arrondi*, puis
        // nouvel arrondi — l'ordre exact de clando.dart::_calculateVipPrice.
        $prix = $vip ? self::arrondi50($classique + $classique * $majorationVip / 100) : $classique;

        return new Devis(
            service: $service,
            distanceKm: $km,
            vip: $vip,
            prix: $prix,
            prixClassique: $classique,
            source: $p ? Devis::SOURCE_PARAMETERS : Devis::SOURCE_DEFAUT,
            tarif: [
                'prix_km' => (int) $prixKm,
                'prix_min' => (int) $prixMin,
                'prix_max' => null,
                'majoration_vip' => $service === Tarif::CLANDO ? $majorationVip : null,
                'debut' => null,
                'fin' => null,
            ],
            calculeA: CarbonImmutable::now(),
        );
    }

    /** Arrondi au multiple de 50 supérieur — aucun prix n'a d'unité sous 50 F. */
    public static function arrondi50(float $montant): int
    {
        return (int) (ceil($montant / 50) * 50);
    }
}
