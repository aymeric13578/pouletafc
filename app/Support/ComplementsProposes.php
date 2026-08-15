<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Quels compléments proposer, et faut-il en proposer.
 *
 * La règle tient en deux phrases mais se trompe facilement :
 *
 *  - dès qu'au moins un produit du panier propose des compléments, on demande
 *    au client s'il en veut ;
 *  - la liste offerte est l'union des compléments de tous les produits, sans
 *    doublon : deux plats accompagnés des mêmes frites ne doivent pas les faire
 *    apparaître deux fois.
 *
 * Elle est ici plutôt que dans un écran parce que trois appelants s'en servent
 * — le panier de l'application, l'écran de commande et la correction d'une
 * commande par le comptoir. Recopiée trois fois, elle finirait par diverger, et
 * c'est le client qui verrait deux listes différentes du même panier.
 */
class ComplementsProposes
{
    /**
     * Compléments à proposer pour un ensemble de produits.
     *
     * @param  iterable<int>  $idsProduits
     * @return Collection<int, Product>
     */
    public function pourProduits(iterable $idsProduits): Collection
    {
        $ids = collect($idsProduits)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        /*
         | Les compléments sont chargés depuis les produits choisis, en une
         | requête. Interroger produit par produit multiplierait les allers-
         | retours au moment précis où le client attend une réponse — l'ajout au
         | panier.
         */
        $produits = Product::with('complements')->whereIn('id', $ids)->get();

        return $produits
            ->flatMap(fn (Product $produit) => $produit->complements)
            /*
             | Sans doublon : deux plats accompagnés des mêmes frites ne doivent
             | pas les faire apparaître deux fois. La clé est l'identifiant, pas
             | le nom : deux boutiques peuvent vendre des « frites » distinctes.
             */
            ->unique('id')
            // Un complément retiré de la vente ne doit plus être proposé, même
            // s'il reste rattaché à un plat.
            ->filter(fn (Product $complement) => $complement->status === 'Success')
            ->sortBy('name')
            ->values();
    }

    /**
     * Faut-il poser la question au client ?
     *
     * Dès qu'un seul produit en propose : demander pour un panier de cinq plats
     * dont un seul a des accompagnements reste utile, alors que se taire
     * priverait le client de l'accompagnement de ce plat-là.
     *
     * @param  iterable<int>  $idsProduits
     */
    public function fautIlDemander(iterable $idsProduits): bool
    {
        return $this->pourProduits($idsProduits)->isNotEmpty();
    }

    /**
     * Tous les produits du panier proposent-ils des compléments ?
     *
     * Distingue « on peut proposer » de « le panier entier s'accompagne ». Dans
     * ce second cas l'écran insiste davantage, la question portant sur la
     * commande dans son ensemble et non sur un plat isolé.
     *
     * @param  iterable<int>  $idsProduits
     */
    public function tousEnProposent(iterable $idsProduits): bool
    {
        $ids = collect($idsProduits)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return false;
        }

        $produits = Product::with('complements')->whereIn('id', $ids)->get();

        // Un identifiant inconnu ne doit pas passer pour un produit accompagné :
        // on exige d'avoir retrouvé chaque produit demandé.
        if ($produits->count() !== $ids->count()) {
            return false;
        }

        return $produits->every(fn (Product $produit) => $produit->complements->isNotEmpty());
    }

    /**
     * Ce que l'application affiche pour décider.
     *
     * @param  iterable<int>  $idsProduits
     * @return array<string, mixed>
     */
    public function charge(iterable $idsProduits): array
    {
        $complements = $this->pourProduits($idsProduits);

        return [
            'demander' => $complements->isNotEmpty(),
            'tous_en_proposent' => $this->tousEnProposent($idsProduits),
            'complements' => $complements->map(fn (Product $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'price' => (int) $c->price,
                'description' => $c->description,
                'image' => $c->product_image1,
                'stock_init' => (int) $c->stock_init,
            ])->values(),
        ];
    }
}
