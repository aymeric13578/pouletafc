<?php

namespace App\Support;

use App\Models\order_detail;

/**
 * Ce que vaut une commande : la somme de ce qu'elle contient.
 *
 * Le montant était lu dans « order_details.price », figé au moment où la commande
 * avait été passée. Quand le panier avait été composé en plusieurs fois — le
 * client commande, revient plus tard, ajoute d'autres produits — cette valeur ne
 * couvrait plus qu'une partie de ce que le comptoir devait préparer : 2 500 F
 * enregistrés pour treize articles en valant 30 000.
 *
 * Deux chiffres qui prétendent dire la même chose et se contredisent, c'est un
 * chiffre de trop. On additionne donc les articles, à chaque affichage. La somme
 * ne peut pas mentir sur ce que contient le panier — c'est ce même panier qu'on
 * a sous les yeux.
 *
 * La valeur enregistrée reste en base : elle sert la comptabilité et les
 * statistiques, et l'écran du comptoir propose de l'aligner quand elle diverge.
 */
class MontantDeCommande
{
    /**
     * Le prix des articles du panier.
     *
     * Renvoie null quand la commande n'a pas de panier — une course de coursier
     * n'en a pas, et son montant n'a rien à voir avec des articles.
     */
    public static function panier(order_detail $commande): ?int
    {
        $articles = $commande->carts?->cart_items;

        if ($articles === null || $articles->isEmpty()) {
            return null;
        }

        return (int) $articles->sum(fn ($ligne) => (float) $ligne->amount * (int) $ligne->quantity);
    }

    /**
     * Le montant à afficher : les articles, plus la livraison.
     *
     * Sans panier exploitable, on retombe sur la valeur enregistrée : mieux vaut
     * le montant d'origine qu'un zéro.
     */
    public static function total(order_detail $commande): int
    {
        $panier = self::panier($commande);

        if ($panier === null) {
            return (int) $commande->price;
        }

        return $panier + (int) $commande->delivery_fees;
    }

    /**
     * Le nombre d'articles réellement au panier.
     */
    public static function quantite(order_detail $commande): int
    {
        $articles = $commande->carts?->cart_items;

        return $articles === null ? (int) $commande->qty : (int) $articles->sum('quantity');
    }

    /**
     * Le montant enregistré diffère-t-il de ce que vaut le panier ?
     *
     * Sert au comptoir : la comptabilité s'appuie sur la valeur enregistrée, et
     * son écart avec la réalité doit se voir plutôt que se corriger tout seul.
     */
    public static function diverge(order_detail $commande): bool
    {
        $panier = self::panier($commande);

        return $panier !== null && $panier !== (int) $commande->panier_price;
    }
}
