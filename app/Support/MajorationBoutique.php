<?php

namespace App\Support;

use App\Models\BoutiqueFacturation;
use Illuminate\Support\Collection;

/**
 * Applique la commission d'une boutique au prix affiché côté client.
 *
 * Un seul endroit pour cette règle, parce qu'elle doit valoir partout où un
 * prix part vers l'application cliente — catalogue, détail d'un produit,
 * recherche, vitrine d'une boutique. Dupliquée, elle finirait par diverger et
 * un même produit s'afficherait à deux prix selon l'écran d'où on le regarde.
 *
 * Ce que voit qui :
 *  - le client voit le prix majoré, et c'est lui qu'il paie ;
 *  - le marchand continue de saisir et de relire son prix de base, la
 *    majoration ne lui est jamais montrée ;
 *  - l'écart revient à l'entreprise.
 *
 * L'arrondi se fait au multiple de 50 supérieur : aucun prix de cet
 * écosystème n'a d'unité en dessous de 50 F CFA, et 1000 F majorés de 5 %
 * donneraient sinon 1050 F d'un côté et 1047,50 F de l'autre selon qui
 * calcule.
 */
class MajorationBoutique
{
    /** Facturations actives, indexées par boutique. Chargées une fois par requête. */
    private ?Collection $parBoutique = null;

    private function facturations(): Collection
    {
        return $this->parBoutique ??= BoutiqueFacturation::where('actif', true)
            ->where('mode', BoutiqueFacturation::MODE_COMMISSION)
            ->with('produits')
            ->get()
            ->keyBy('shop_id');
    }

    /**
     * Prix affiché au client pour ce produit, majoration comprise.
     *
     * Retourne le prix de base inchangé quand la boutique n'est pas en
     * commission, que le produit n'est pas dans la sélection majorée, ou que
     * le taux est nul.
     */
    public function prixAffiche(float $prixBase, ?int $shopId, ?int $produitId): int
    {
        $taux = $this->taux($shopId, $produitId);

        if ($taux === null) {
            return self::arrondi($prixBase);
        }

        return self::arrondi($prixBase * (1 + $taux / 100));
    }

    /** Ce que l'entreprise perçoit sur ce produit, à l'unité. */
    public function commission(float $prixBase, ?int $shopId, ?int $produitId): int
    {
        return max(0, $this->prixAffiche($prixBase, $shopId, $produitId) - (int) round($prixBase));
    }

    /** Taux applicable, ou null si rien n'est majoré. */
    public function taux(?int $shopId, ?int $produitId): ?float
    {
        if ($shopId === null || $produitId === null) {
            return null;
        }

        return $this->facturations()->get($shopId)?->tauxPour($produitId);
    }

    /** Multiple de 50 supérieur. */
    public static function arrondi(float $montant): int
    {
        return (int) (ceil(max($montant, 0) / 50) * 50);
    }
}
