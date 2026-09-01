<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Shop;
use App\Models\User;
use App\Support\CommandeSansDoublon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reçoit un panier composé sur le téléphone, et en fait une commande.
 *
 * Le panier était construit sur le serveur, un aller-retour par geste : ajouter
 * un produit, changer une quantité, en retirer un. Trois conséquences, toutes
 * constatées en production :
 *
 *  - sans réseau, le panier devenait inutilisable ;
 *  - chaque appel pouvait échouer à mi-chemin, et deux files d'attente avaient
 *    été ajoutées pour les rejouer — d'où des paniers en double, puis des
 *    commandes en double ;
 *  - l'écran d'ajout et l'écran du panier ne regardaient pas toujours le même
 *    panier du client.
 *
 * Un panier n'est qu'une liste que le client compose pour lui-même : il n'a rien
 * à faire sur un serveur avant d'être commandé. Il arrive donc ici d'un bloc, au
 * moment de la validation, et tout est écrit dans une seule transaction — le
 * panier, ses articles et la commande. Il n'y a plus d'état intermédiaire à
 * rattraper.
 */
class PanierValideController extends Controller
{
    public function __invoke(Request $request, CommandeSansDoublon $sansDoublon): JsonResponse
    {
        $client = User::find($request->input('user_id'));

        if (! $client) {
            return response()->json(['response' => 404, 'message' => 'Client introuvable.']);
        }

        $articles = $this->articles($request);

        if ($articles->isEmpty()) {
            return response()->json(['response' => 400, 'message' => 'Le panier est vide.']);
        }

        /*
         | Un même panier peut mêler plusieurs boutiques (voir
         | MaBoutiqueController::commandesDe) : chacune est vérifiée, pas
         | seulement la première. La fenêtre entre "le client compose son
         | panier" et "il valide" peut suffire à faire fermer une boutique
         | entre-temps — l'écran l'affichait peut-être ouverte au moment de
         | l'ajout, rien ne le revérifiait à la validation.
         */
        $boutiquesFermees = $articles
            ->map(fn ($article) => $article['produit']->shop)
            ->filter()
            ->unique('id')
            ->filter(fn (Shop $boutique) => ! $boutique->estOuverteMaintenant());

        if ($boutiquesFermees->isNotEmpty()) {
            // La prochaine heure d'ouverture n'est annoncée que pour une
            // boutique unique : avec plusieurs boutiques fermées, chacune a
            // potentiellement une réouverture différente et le message
            // deviendrait illisible à énumérer.
            if ($boutiquesFermees->count() === 1) {
                $boutique = $boutiquesFermees->first();
                $prochaineOuverture = $boutique->prochaineOuverture();
                $message = "La boutique « {$boutique->shop_name} » est actuellement fermée."
                    . ($prochaineOuverture ? " Réouverture prévue {$prochaineOuverture}." : '');
            } else {
                $message = 'Ces boutiques de votre panier sont actuellement fermées : '
                    . $boutiquesFermees->pluck('shop_name')->implode(', ') . '.';
            }

            return response()->json([
                'response' => 409,
                'message' => $message,
            ]);
        }

        $cle = trim((string) $request->input('cle_unique'));

        /*
        | Une tentative rejouée ne recommande pas.
        |
        | L'application renvoie la même clé quand elle réessaie — après un délai
        | dépassé, par exemple, où la commande a souvent été créée sans que la
        | réponse revienne. On retourne alors la commande d'origine, telle quelle.
        */
        $existante = $sansDoublon->parCle($cle);

        if ($existante) {
            $sansDoublon->signaler($existante, null);

            return response()->json([
                'response' => 200,
                'data' => $existante,
                'doublon' => true,
                'message' => 'Cette commande était déjà enregistrée.',
            ]);
        }

        /*
         | Promotions actives sur les produits du panier (voir
         | ProductsController::getAllProducts, qui applique déjà cette même
         | remise à l'affichage) — récupérées avant la transaction, pas
         | pour chaque article, pour éviter une requête par ligne de panier.
         | Sans elles, le total encaissé ignorait la remise que le client
         | venait de voir à l'écran : la commande enregistrée facturait le
         | plein tarif pendant qu'une promotion était pourtant active.
         */
        $promotions = Promotion::where('status', 'Success')
            ->whereIn('id_product', $articles->pluck('produit.id'))
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get()
            ->keyBy('id_product');

        // Commissions de boutique, chargées une fois pour tout le panier.
        $majoration = app(\App\Support\MajorationBoutique::class);

        try {
            $commande = DB::transaction(function () use ($request, $client, $articles, $cle, $promotions, $majoration) {
                $panier = Cart::create([
                    'user_id' => $client->id,
                    'status' => 'Success',
                    'total_amount' => 0,
                ]);

                $total = 0;

                foreach ($articles as $article) {
                    /*
                     | Le prix est celui de la base, jamais celui transmis par le
                     | téléphone : un panier tenu sur l'appareil du client ne doit
                     | pas pouvoir dicter le montant de sa commande. La promotion
                     | active, elle, est appliquée ici — recalculée depuis la
                     | base, jamais depuis un total envoyé par l'application.
                     */
                    /*
                     | La commission de boutique est appliquée ici, et dans le
                     | même ordre que sur le catalogue (majoration, puis
                     | promotion) : sans cela, le client verrait 1 050 F à
                     | l'affichage et serait facturé 1 000 F — la commission
                     | n'atteindrait jamais l'entreprise, et le total de la
                     | commande contredirait le panier que le client vient de
                     | relire. Recalculée depuis la base comme le reste, elle
                     | ne dépend pas davantage de ce qu'envoie le téléphone.
                     */
                    $produit = $article['produit'];
                    $prixBase = $majoration->prixAffiche(
                        (float) $produit->price,
                        $produit->id_shop,
                        $produit->id
                    );
                    $promotion = $promotions->get($produit->id);
                    $montant = $promotion ? $promotion->prixApres((float) $prixBase) : (float) $prixBase;

                    /*
                     | Part de majoration figée au moment de la vente (Phase 2,
                     | livre de comptes) : le taux peut changer demain, la
                     | ventilation « net marchand / part société » de CETTE
                     | vente, elle, ne doit plus bouger.
                     |
                     | En promotion, la remise s'applique au prix majoré : la
                     | part société est réduite dans la même proportion, si
                     | bien que le marchand encaisse exactement son prix promo
                     | (prix de base × taux de promo) — c'est lui qui décide
                     | de ses promotions, la plateforme ne s'enrichit ni ne
                     | s'appauvrit sur cette décision.
                     */
                    $majorationUnitaire = $prixBase > 0
                        ? $montant * (($prixBase - (float) $produit->price) / $prixBase)
                        : 0.0;

                    CartItem::create([
                        'user_id' => $client->id,
                        'cart_id' => $panier->id,
                        'product_id' => $produit->id,
                        'quantity' => $article['quantite'],
                        'amount' => $montant,
                        'majoration_unitaire' => round($majorationUnitaire, 2),
                        'status' => 'Success',
                    ]);

                    $total += $montant * $article['quantite'];
                }

                $panier->update(['total_amount' => $total]);

                return app(OrderController::class)->creerDepuisPanier(
                    $request,
                    $client,
                    $panier,
                    $total,
                    $articles->sum('quantite'),
                    $cle
                );
            });
        } catch (\Throwable $e) {
            Log::error('Validation de panier impossible', [
                'client' => $client->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'response' => 500,
                'message' => "La commande n'a pas pu être enregistrée. Réessayez.",
            ]);
        }

        return response()->json([
            'response' => 200,
            'data' => $commande,
            'doublon' => false,
        ]);
    }

    /**
     * Les articles reçus, rapprochés des produits réels.
     *
     * Un produit inconnu ou retiré de la vente est écarté sans faire échouer la
     * commande : le client n'a pas à voir sa validation refusée parce qu'un
     * article a disparu du catalogue pendant qu'il composait son panier.
     */
    private function articles(Request $request)
    {
        $recus = $request->input('articles');

        if (is_string($recus)) {
            $recus = json_decode($recus, true);
        }

        $recus = collect(is_array($recus) ? $recus : []);

        if ($recus->isEmpty()) {
            return collect();
        }

        $produits = Product::whereIn('id', $recus->pluck('product_id')->filter()->all())
            ->where('status', 'Success')
            ->with('shop')
            ->get()
            ->keyBy('id');

        return $recus
            ->map(function ($article) use ($produits) {
                $produit = $produits->get((int) ($article['product_id'] ?? 0));

                if (! $produit) {
                    return null;
                }

                return [
                    'produit' => $produit,
                    // Une quantité absurde — zéro, négative, ou saisie à côté —
                    // ne doit pas devenir une commande de mille poulets.
                    'quantite' => max(1, min(99, (int) ($article['quantity'] ?? 1))),
                ];
            })
            ->filter()
            ->values();
    }
}
