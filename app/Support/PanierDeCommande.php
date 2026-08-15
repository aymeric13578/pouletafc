<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\order_detail;
use App\Models\Product;

/**
 * Modification du panier d'une commande passée.
 *
 * Le comptoir corrige souvent une commande après coup : un article en rupture,
 * une quantité mal comprise au téléphone, un complément ajouté à la dernière
 * minute. Sans cela il fallait tout annuler et ressaisir, en perdant
 * l'historique et l'agent déjà attribué.
 *
 * La règle vit ici plutôt que dans les écrans parce que deux endroits s'en
 * servent — le mur des commandes et l'administration. Recopiée, elle finirait
 * par diverger, et deux écrans donneraient deux totaux pour la même commande.
 */
class PanierDeCommande
{
    /** Une commande close ne se corrige plus. */
    public const CLOSES = ['Success', 'failed'];

    /**
     * Cette commande peut-elle encore être corrigée ?
     *
     * Une commande livrée et encaissée ne doit plus changer de montant : la
     * corriger après coup fausserait les comptes sans laisser de trace.
     */
    public function modifiable(order_detail $commande): bool
    {
        return ! in_array($commande->status, self::CLOSES, true)
            && $commande->id_cart !== null;
    }

    /**
     * Lignes du panier, avec leur produit.
     */
    public function lignes(order_detail $commande)
    {
        if (! $commande->id_cart) {
            return collect();
        }

        /*
         | Produit chargé en entier, sans liste de colonnes.
         |
         | Restreindre la sélection fait dépendre l'écran de la présence exacte
         | de chaque colonne nommée : une seule absente, et toute la requête
         | échoue. Sur un panier de quelques lignes, l'économie ne valait pas ce
         | risque.
         */
        return CartItem::with('product')
            ->where('cart_id', $commande->id_cart)
            ->orderBy('id')
            ->get();
    }

    /**
     * Ajoute un produit, ou augmente la quantité s'il y est déjà.
     *
     * Le même article deux fois donnerait deux lignes qui se corrigent mal et
     * s'additionnent mal à l'œil.
     */
    public function ajouter(order_detail $commande, Product $produit, int $quantite = 1): CartItem
    {
        $ligne = CartItem::where('cart_id', $commande->id_cart)
            ->where('product_id', $produit->id)
            ->first();

        if ($ligne) {
            $ligne->update(['quantity' => (int) $ligne->quantity + $quantite]);
        } else {
            $ligne = CartItem::create(
                $this->donneesDeLigne($commande->id_cart, $commande->id_user, $produit, $quantite)
            );
        }

        $this->recalculer($commande);

        return $ligne;
    }

    /**
     * Champs d'une nouvelle ligne de panier.
     *
     * Publique parce qu'elle fait autorité : tout ce qui crée une ligne doit
     * passer par elle, tests compris. Une ligne fabriquée à côté masquerait le
     * défaut qu'elle prémunit.
     *
     * Calqué sur ce qu'écrit CartController quand le client ajoute au panier :
     * même table, mêmes contraintes. En omettre un rendait l'insertion
     * impossible en production sans que rien ne le laisse voir ici, le schéma
     * reconstruit depuis les migrations ne portant pas ces colonnes.
     *
     * @return array<string, mixed>
     */
    public function donneesDeLigne(?int $idPanier, ?int $idClient, Product $produit, int $quantite): array
    {
        $donnees = [
            'cart_id' => $idPanier,
            'product_id' => $produit->id,
            // Prix figé à l'ajout, comme au panier du client : il ne doit pas
            // suivre les changements de tarif ultérieurs.
            'amount' => (int) $produit->price,
            'quantity' => $quantite,
        ];

        /*
         | Colonnes présentes en production mais absentes du schéma décrit par
         | les migrations. On ne les écrit que si elles existent : les écrire
         | toujours ferait échouer l'insertion là où elles manquent, et les
         | omettre toujours la fait échouer là où elles sont obligatoires.
         */
        if (self::colonneExiste('user_id')) {
            $donnees['user_id'] = $idClient;
        }

        if (self::colonneExiste('status')) {
            $donnees['status'] = 'Success';
        }

        return $donnees;
    }

    /**
     * Cette colonne existe-t-elle sur cart_items ?
     *
     * Retenu d'un appel à l'autre : la question se pose à chaque ajout, et la
     * réponse ne change pas en cours de requête.
     *
     * @var array<string, bool>
     */
    private static array $colonnes = [];

    private static function colonneExiste(string $colonne): bool
    {
        return self::$colonnes[$colonne] ??= \Illuminate\Support\Facades\Schema::hasColumn('cart_items', $colonne);
    }

    /**
     * Fixe la quantité d'une ligne.
     *
     * Une quantité tombée à zéro retire la ligne : laisser un article à zéro
     * dans une commande n'a pas de sens et se relit comme une erreur.
     */
    public function definirQuantite(order_detail $commande, CartItem $ligne, int $quantite): void
    {
        if ($quantite < 1) {
            $this->retirer($commande, $ligne);

            return;
        }

        $ligne->update(['quantity' => $quantite]);
        $this->recalculer($commande);
    }

    public function retirer(order_detail $commande, CartItem $ligne): void
    {
        $ligne->delete();
        $this->recalculer($commande);
    }

    /**
     * Réaligne le montant de la commande sur son panier.
     *
     * Le total est recalculé depuis les lignes plutôt qu'ajusté du montant
     * touché : une commande déjà corrigée au poids, ou dont un tarif a bougé,
     * dériverait sinon un peu plus à chaque geste.
     */
    public function recalculer(order_detail $commande): int
    {
        $panier = (int) CartItem::where('cart_id', $commande->id_cart)
            ->get()
            ->sum(fn (CartItem $ligne) => (int) $ligne->quantity * (int) $ligne->amount);

        $commande->update([
            'panier_price' => $panier,
            'price' => $panier + (int) $commande->delivery_fees,
            /*
             | Le poids saisi devient faux dès que le panier change : le laisser
             | ferait croire que le montant en découle encore. Le comptoir le
             | ressaisira s'il pèse à nouveau.
             */
            'poids_kg' => null,
        ]);

        return $panier;
    }

    /**
     * Total du panier tel qu'il est, sans écrire.
     */
    public function total(order_detail $commande): int
    {
        return (int) $this->lignes($commande)
            ->sum(fn (CartItem $ligne) => (int) $ligne->quantity * (int) $ligne->amount);
    }
}
