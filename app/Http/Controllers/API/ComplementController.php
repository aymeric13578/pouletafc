<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\ComplementsProposes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compléments proposés au client.
 *
 * Un complément est un produit marqué comme tel et rattaché aux plats qui le
 * proposent. L'application interroge ces routes à deux moments : quand le
 * client ajoute un produit, et quand il s'apprête à valider son panier.
 */
class ComplementController extends Controller
{
    public function __construct(private readonly ComplementsProposes $regle)
    {
    }

    /**
     * Compléments d'un produit précis.
     *
     * Appelé au moment de l'ajout au panier : « ce poulet se prend avec des
     * frites, en voulez-vous ? ».
     */
    public function getProductComplements(Request $request): JsonResponse
    {
        $idProduit = (int) $request->input('id_product');

        if (! $idProduit) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant produit manquant',
                'data' => null,
            ]);
        }

        return response()->json([
            'response' => 200,
            'data' => $this->regle->charge([$idProduit]),
        ]);
    }

    /**
     * Compléments proposés pour le panier entier.
     *
     * Appelé avant la validation : la liste est l'union sans doublon des
     * compléments de tous les produits du panier. Deux plats accompagnés des
     * mêmes frites ne doivent pas les faire apparaître deux fois.
     *
     * `product_ids` (liste d'ids séparés par des virgules) est le chemin
     * attendu depuis le panier local de plouletafcapp (LocalCartService) :
     * depuis son passage en panier 100% local, plus aucun Cart/CartItem
     * serveur n'existe avant validerPanier, donc plus rien à lire côté
     * `panierDe()`. Le repli sur l'ancien Cart serveur reste en place pour
     * un éventuel appelant qui en dépendrait encore.
     */
    public function getCartComplements(Request $request): JsonResponse
    {
        if ($request->filled('product_ids')) {
            $ids = collect(explode(',', (string) $request->input('product_ids')))
                ->map(fn ($id) => (int) trim($id))
                ->filter();

            return response()->json([
                'response' => 200,
                'data' => $this->regle->charge($ids),
            ]);
        }

        $panier = $this->panierDe($request);

        if (! $panier) {
            return response()->json([
                'response' => 200,
                // Panier vide : réponse normale, pas une erreur.
                'data' => $this->regle->charge([]),
            ]);
        }

        $produits = CartItem::where('cart_id', $panier->id)->pluck('product_id');

        return response()->json([
            'response' => 200,
            'data' => $this->regle->charge($produits),
        ]);
    }

    /**
     * Panier en cours du client, s'il en a un.
     */
    private function panierDe(Request $request): ?Cart
    {
        $idUser = $request->input('id_user');

        if (! $idUser) {
            return null;
        }

        return Cart::where('user_id', $idUser)->orderByDesc('id')->first();
    }
}
