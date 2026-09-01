<?php

namespace App\Support;

use App\Models\MouvementFinancier;
use App\Models\order_detail;

/**
 * Ventile une commande payée Orange Money entre les boutiques concernées et
 * la société — Phase 2 du livre de comptes.
 *
 * Une commande peut mêler plusieurs boutiques (même logique de filtrage que
 * MaBoutiqueController::getMyShopFinance) : chaque boutique est créditée de
 * la somme de SES lignes de panier, nette de la majoration figée à la vente
 * (cart_items.majoration_unitaire) ; la somme des majorations revient à la
 * société. Les produits de Poulet AFC lui-même (id_shop nul ou boutique
 * inexistante) ne créent pas de ligne boutique : l'argent est déjà chez la
 * société, il n'y a personne d'autre à créditer.
 *
 * Idempotent : LivreDeComptes déduplique par (type, acteur, source) — le
 * même paiement revérifié deux fois ne crédite personne deux fois.
 */
class VentilationVenteOm
{
    public static function crediter(int $idOrderDetail): void
    {
        $commande = order_detail::with('carts.cart_items.product:id,id_shop')
            ->find($idOrderDetail);

        if (! $commande) {
            return;
        }

        $lignes = $commande->carts?->cart_items ?? collect();
        $livre = app(LivreDeComptes::class);
        $ref = (string) $commande->ref;

        $parBoutique = $lignes
            ->filter(fn ($item) => $item->product?->id_shop !== null)
            ->groupBy(fn ($item) => $item->product->id_shop);

        foreach ($parBoutique as $idBoutique => $items) {
            $brut = (float) $items->sum(fn ($i) => $i->amount * $i->quantity);
            $majoration = (float) $items->sum(fn ($i) => ($i->majoration_unitaire ?? 0) * $i->quantity);

            $livre->venteOm(
                (int) $idBoutique,
                round($brut - $majoration, 2),
                round($majoration, 2),
                'order',
                $commande->id,
                $ref,
            );
        }
    }
}
