<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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
     */
    public function getCartComplements(Request $request): JsonResponse
    {
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
     * Ajoute un complément au panier du client.
     *
     * Un complément étant un produit, il rejoint le panier comme n'importe quel
     * article : rien de séparé à maintenir, et le montant se calcule de la même
     * façon.
     */
    public function addComplementToCart(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_user' => ['required', 'integer'],
            'id_complement' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $complement = Product::findOrFail($valide['id_complement']);

        if (! $complement->is_complement) {
            /*
             | Refusé explicitement : cette route sert à accompagner un plat.
             | Laisser passer n'importe quel produit en ferait une seconde voie
             | d'ajout au panier, avec ses propres règles à maintenir.
             */
            return response()->json([
                'response' => 422,
                'message' => "Ce produit n'est pas un complément.",
            ]);
        }

        $panier = $this->panierDe($request) ?? Cart::create([
            'user_id' => $valide['id_user'],
            'total_amount' => 0,
        ]);

        $quantite = (int) ($valide['quantity'] ?? 1);

        $existante = CartItem::where('cart_id', $panier->id)
            ->where('product_id', $complement->id)
            ->first();

        if ($existante) {
            $existante->update(['quantity' => $existante->quantity + $quantite]);
        } else {
            /*
             | Sans user_id : la colonne n'existe pas dans le schéma des
             | migrations, et le panier porte déjà son propriétaire. L'écrire
             | ferait dépendre l'ajout d'une colonne dont la présence n'est pas
             | garantie d'une base à l'autre.
             */
            CartItem::create([
                'cart_id' => $panier->id,
                'product_id' => $complement->id,
                'quantity' => $quantite,
                // Prix figé à l'ajout, comme pour tout article du panier.
                'amount' => (int) $complement->price,
            ]);
        }

        return response()->json([
            'response' => 200,
            'message' => $complement->name . ' ajouté à votre commande.',
            'data' => ['id_cart' => $panier->id],
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
