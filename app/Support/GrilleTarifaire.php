<?php

namespace App\Support;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;

/**
 * Le tarif qui s'applique à un service, à l'instant présent.
 *
 * Point d'entrée unique entre les deux générations de tarification :
 *
 *  - la grille par service et par plage horaire (`tarifs`/`tarif_plages`),
 *    qui l'emporte dès qu'elle existe pour le service demandé ;
 *  - l'ancienne ligne plate de `parameters`, conservée comme repli.
 *
 * Ce repli n'est pas de la prudence excessive : `parameters` reste lue par
 * l'endpoint `getParameters`, que consomment les trois applications mobiles
 * (CLAUDE.md règle 1). Tant qu'aucune grille n'est créée pour un service, ce
 * service doit continuer de facturer exactement comme avant — créer les
 * tables ne doit pas changer un seul prix.
 *
 * Les commissions étant de l'argent réellement versé aux agents, aucune
 * valeur par défaut inventée : sans grille et sans `parameters`, la
 * commission vaut 0, comme c'était déjà le cas.
 */
class GrilleTarifaire
{
    /** @var array<string, TarifPlage|null> Plage courante, par service. */
    private array $plages = [];

    private ?Parameter $parametres = null;

    /** La plage horaire applicable à ce service, ou null si aucune grille. */
    public function plage(string $service): ?TarifPlage
    {
        if (! array_key_exists($service, $this->plages)) {
            $this->plages[$service] = Tarif::actif($service)?->plageCourante();
        }

        return $this->plages[$service];
    }

    /**
     * La ligne plate `parameters` active, ou null. Publique pour que le
     * moteur de tarification (App\Support\Tarification) puisse appliquer le
     * même repli que les commissions ci-dessous — sans dupliquer la lecture.
     */
    public function parametres(): ?Parameter
    {
        return $this->parametres ??= Parameter::active();
    }

    /**
     * Commission retenue sur une course clando.
     *
     * Repli : `clando_agent_commission`, appliqué au prix de la course — le
     * comportement en place avant les grilles.
     */
    public function commissionClando(float $prix, bool $vip = false): float
    {
        $plage = $this->plage(Tarif::CLANDO);

        if ($plage) {
            return $plage->commissionPour($prix, $vip);
        }

        return round($prix * (float) ($this->parametres()->clando_agent_commission ?? 0) / 100);
    }

    /**
     * Commission retenue sur une livraison de boutique.
     *
     * Elle porte sur les **seuls frais de livraison**, jamais sur le panier :
     * l'entreprise se rémunère sur le service de portage, pas sur la valeur
     * des marchandises qu'un marchand tiers a vendues.
     *
     * Ce n'était pas le cas — la commission était calculée sur le total des
     * articles, et avec le taux clando par-dessus le marché. Le repli garde
     * malgré tout l'ancien calcul tant qu'aucune grille « livraison » n'a été
     * créée : basculer d'un coup la rémunération des livreurs en production,
     * sans que personne ne l'ait demandé écran en main, serait la pire façon
     * d'introduire cette correction. Créer la grille suffit à l'appliquer.
     *
     * @param float $fraisDeLivraison Frais de portage facturés au client.
     * @param float $totalArticles    Montant du panier, pour le seul repli.
     */
    public function commissionLivraison(float $fraisDeLivraison, float $totalArticles): float
    {
        $plage = $this->plage(Tarif::LIVRAISON);

        if ($plage) {
            return $plage->commissionPour($fraisDeLivraison);
        }

        return round($totalArticles * (float) ($this->parametres()->clando_agent_commission ?? 0) / 100);
    }

    /** Commission retenue sur une course de coursier, sur son prix. */
    public function commissionCoursier(float $prix): float
    {
        $plage = $this->plage(Tarif::COURSIER);

        if ($plage) {
            return $plage->commissionPour($prix);
        }

        return round($prix * (float) ($this->parametres()->clando_agent_commission ?? 0) / 100);
    }

    /**
     * Tarifs d'un service tels qu'une application mobile peut les lire.
     *
     * Renvoie null quand aucune grille n'existe : l'application garde alors
     * les champs plats de `parameters` qu'elle lit déjà, sans avoir à
     * distinguer les deux cas.
     *
     * @return array<string, mixed>|null
     */
    public function pourApplication(string $service): ?array
    {
        $plage = $this->plage($service);

        if (! $plage) {
            return null;
        }

        return [
            'debut' => $plage->debutCourt(),
            'fin' => $plage->finCourte(),
            'prix_km' => $plage->prix_km,
            'prix_min' => $plage->prix_min,
            'prix_max' => $plage->prix_max,
            'commission' => $plage->commission,
            'commission_vip' => $plage->commission_vip,
            'majoration_vip' => $plage->majoration_vip,
        ];
    }
}
