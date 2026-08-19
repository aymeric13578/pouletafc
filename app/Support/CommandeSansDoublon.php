<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\order_detail;
use Illuminate\Support\Facades\Log;

/**
 * Reconnaît une commande déjà passée, pour ne pas la passer deux fois.
 *
 * Le seul garde-fou existant comparait (client, panier) : une seconde tentative
 * sur le même panier retombait bien sur la première commande. Mais le panier
 * était marqué « Success » dès le début de la création, si bien que l'écran du
 * panier en ouvrait aussitôt un neuf. Le client qui ne voyait pas sa
 * confirmation — réseau lent, réponse perdue — réappuyait, obtenait un panier
 * neuf, et donc une commande de plus. Le garde-fou ne se déclenchait jamais.
 *
 * Constaté en production : trois commandes identiques d'un même client, paniers
 * 542, 543 et 544, créés à 36 secondes d'intervalle, un seul produit à chaque
 * fois. Le client n'a pas commandé trois fois ; il a appuyé trois fois.
 *
 * On reconnaît donc une commande par ce qu'elle contient, et non par le panier
 * qui la porte. Ce contrôle vaut pour toutes les versions déjà installées, ce
 * qui compte : on ne peut pas attendre que tous les téléphones soient à jour.
 */
class CommandeSansDoublon
{
    /**
     * Fenêtre pendant laquelle deux commandes identiques n'en font qu'une.
     *
     * Cinq minutes : assez large pour couvrir un client qui réessaie plusieurs
     * fois sur un réseau capricieux, assez courte pour qu'une seconde commande
     * réellement voulue passe — personne ne recommande le même repas identique
     * dans les cinq minutes sans s'en rendre compte.
     */
    private const FENETRE_MINUTES = 5;

    /**
     * La commande équivalente déjà enregistrée, s'il y en a une.
     */
    public function dejaPassee(int $idClient, ?int $idPanier, float $prix, ?string $adresse): ?order_detail
    {
        $depuis = now()->subMinutes(self::FENETRE_MINUTES);

        $recentes = order_detail::where('id_user', $idClient)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->get();

        if ($recentes->isEmpty()) {
            return null;
        }

        // Même panier : c'est le cas simple, et le seul que couvrait l'ancien
        // contrôle. On le garde, il reste le plus sûr.
        if ($idPanier) {
            $memePanier = $recentes->firstWhere('id_cart', $idPanier);

            if ($memePanier) {
                return $memePanier;
            }
        }

        $signature = $this->signatureDuPanier($idPanier);

        foreach ($recentes as $commande) {
            /*
             | Deux paniers distincts au contenu identique : c'est la trace du
             | client qui réappuie. On compare les produits et leurs quantités,
             | pas le montant seul — deux commandes au même prix peuvent être
             | deux repas différents.
             */
            if ($signature !== '' && $this->signatureDuPanier($commande->id_cart) === $signature) {
                return $commande;
            }

            /*
             | Une course de coursier n'a pas de panier. On se rabat alors sur le
             | prix et l'adresse, les deux seules choses qui la décrivent.
             */
            if ($signature === '' && $commande->id_cart === null
                && (int) $commande->price === (int) $prix
                && $adresse !== null
                && $commande->address === $adresse) {
                return $commande;
            }
        }

        return null;
    }

    /**
     * Ce que contient un panier, sous une forme comparable.
     *
     * Trié : deux paniers aux mêmes produits saisis dans un ordre différent
     * doivent donner la même signature, sinon le doublon passe entre les mailles.
     */
    private function signatureDuPanier(?int $idPanier): string
    {
        if (! $idPanier) {
            return '';
        }

        $lignes = CartItem::where('cart_id', $idPanier)
            ->where('status', 'Success')
            ->get(['product_id', 'quantity'])
            ->map(fn ($ligne) => $ligne->product_id . 'x' . $ligne->quantity)
            ->sort()
            ->values()
            ->all();

        return implode('|', $lignes);
    }

    /**
     * Journalise le doublon écarté.
     *
     * Sans cette trace, la correction devient invisible : on ne saurait pas si
     * les doublons ont cessé parce qu'on les écarte, ou parce que les clients
     * ont changé d'habitude.
     */
    public function signaler(order_detail $existante, ?int $idPanierRejete): void
    {
        Log::info('Commande en double écartée', [
            'commande_conservee' => $existante->ref,
            'client' => $existante->id_user,
            'panier_conserve' => $existante->id_cart,
            'panier_rejete' => $idPanierRejete,
        ]);
    }
}
