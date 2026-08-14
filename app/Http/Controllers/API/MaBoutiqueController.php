<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Espace boutique dans l'application, pendant du module « Ma boutique » du
 * tableau de bord.
 *
 * Un marchand qui se connecte depuis son téléphone n'avait aucun moyen de voir
 * sa vitrine ni ses commandes : il fallait ouvrir le tableau de bord sur un
 * ordinateur. Ces endpoints lui rendent depuis l'application ce qu'il y trouve.
 *
 * Le rattachement passe par shops.id_user, exactement comme sur le tableau de
 * bord. Toutes les requêtes repartent de la boutique de l'appelant plutôt que
 * d'un identifiant transmis : sinon n'importe qui lirait et modifierait la
 * boutique d'un autre en changeant un paramètre.
 */
class MaBoutiqueController extends Controller
{
    /** Boutique rattachée à cet utilisateur, ou null. */
    private function boutiqueDe($idUser): ?Shop
    {
        if (! $idUser) {
            return null;
        }

        return Shop::where('id_user', $idUser)->first();
    }

    /**
     * Ce que l'application demande juste après la connexion pour savoir s'il
     * faut proposer l'entrée « Ma boutique ».
     */
    public function getMyShop(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueDe($request->input('id_user'));

        if (! $boutique) {
            // Réponse normale : la plupart des comptes ne tiennent pas de
            // boutique. L'application masque simplement l'entrée.
            return response()->json(['response' => 200, 'data' => null]);
        }

        $produits = Product::where('id_shop', $boutique->id);

        return response()->json([
            'response' => 200,
            'data' => [
                'id' => $boutique->id,
                'shop_name' => $boutique->shop_name,
                'city' => $boutique->city,
                'address' => $boutique->address,
                'phone1' => $boutique->phone1,
                'phone2' => $boutique->phone2,
                'email1' => $boutique->email1,
                'email2' => $boutique->email2,
                'description' => $boutique->description,
                'status' => $boutique->status,
                'logo' => $boutique->logo ? url('upload/' . $boutique->logo) : null,
                'stats' => [
                    'produits' => (clone $produits)->count(),
                    'commandes' => $this->commandesDe($boutique->id)->count(),
                    'en_cours' => $this->commandesDe($boutique->id)
                        ->whereIn('status', ['pending', 'want', 'take', 'process'])
                        ->count(),
                ],
            ],
        ]);
    }

    /**
     * Fiche modifiable par le marchand.
     *
     * Il ne pilote que sa vitrine. Le statut, le type et le rattachement du
     * responsable restent du ressort de l'équipe : les exposer permettrait de
     * réactiver une boutique désactivée ou de se rattacher ailleurs. C'est la
     * règle déjà posée sur le tableau de bord, reprise à l'identique.
     */
    public function updateMyShop(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueDe($request->input('id_user'));

        if (! $boutique) {
            return response()->json([
                'response' => 404,
                'message' => "Aucune boutique n'est rattachée à ce compte.",
            ]);
        }

        $valide = $request->validate([
            'shop_name' => ['required', 'string', 'max:191'],
            'city' => ['nullable', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:191'],
            'phone1' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'email1' => ['nullable', 'email', 'max:191'],
            'email2' => ['nullable', 'email', 'max:191'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('logo')) {
            $nom = uniqid('logo_', true) . '.' . $request->file('logo')->getClientOriginalExtension();

            try {
                // Même dossier que les autres images du site : le tableau de
                // bord et la vitrine les y servent déjà.
                $request->file('logo')->move(public_path('upload'), $nom);
                $valide['logo'] = $nom;
            } catch (\Throwable $e) {
                Log::warning('Logo de boutique non enregistré : ' . $e->getMessage());
                unset($valide['logo']);
            }
        } else {
            unset($valide['logo']);
        }

        $boutique->update($valide);

        return response()->json([
            'response' => 200,
            'message' => 'Boutique mise à jour.',
        ]);
    }

    /** Produits de la boutique de l'appelant. */
    public function getMyShopProducts(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueDe($request->input('id_user'));

        if (! $boutique) {
            return response()->json(['response' => 404, 'data' => []]);
        }

        $produits = Product::where('id_shop', $boutique->id)
            ->orderByDesc('id')
            ->get(['id', 'name', 'description', 'price', 'product_image1', 'ref', 'status', 'id_category', 'stock_init']);

        return response()->json([
            'response' => 200,
            'data' => $produits->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => (float) $p->price,
                'ref' => $p->ref,
                'status' => $p->status,
                'image' => $p->product_image1,
                // Catégorie et stock servent à pré-remplir le formulaire de
                // modification : sans eux, rouvrir un produit les effacerait.
                'id_category' => $p->id_category,
                'stock_init' => $p->stock_init,
            ])->values(),
        ]);
    }

    /**
     * Commandes contenant au moins un produit de la boutique.
     *
     * Le lien passe par le panier et non par une colonne de la commande : un
     * même panier peut mêler plusieurs boutiques, et order_details ne porte pas
     * l'identifiant de celle qui fournit. C'est la même jointure que sur le
     * tableau de bord.
     */
    public function getMyShopOrders(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueDe($request->input('id_user'));

        if (! $boutique) {
            return response()->json(['response' => 404, 'data' => []]);
        }

        $commandes = $this->commandesDe($boutique->id)
            ->with(['user:id,name,phone,whatsapp'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'response' => 200,
            'data' => $commandes->map(fn (order_detail $o) => [
                'id' => $o->id,
                'ref' => $o->ref,
                'status' => $o->status,
                'price' => (int) $o->price,
                'address' => $o->address,
                'created_at' => $o->created_at?->toIso8601String(),
                // Le marchand prépare une commande ; il n'a pas à disposer du
                // fichier client. Même réserve que sur le tableau de bord.
                'client' => $o->user?->name,
            ])->values(),
        ]);
    }

    /**
     * Catégories proposées au marchand quand il crée un produit.
     *
     * id_category est contrôlé à l'enregistrement : sans cette liste,
     * l'application devrait deviner des identifiants.
     */
    public function getCategories(): JsonResponse
    {
        return response()->json([
            'response' => 200,
            'data' => \App\Models\Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Crée ou modifie un produit de la boutique de l'appelant.
     *
     * Les règles sont celles de l'écran marchand du tableau de bord, reprises
     * à l'identique : mêmes champs obligatoires, même statut d'attente à la
     * création, et surtout la même portée — un produit se retrouve par
     * where('id_shop')->findOrFail(), jamais par findOrFail seul, qui
     * accepterait le produit de n'importe quelle boutique.
     */
    public function saveMyShopProduct(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueDe($request->input('id_user'));

        if (! $boutique) {
            return response()->json([
                'response' => 404,
                'message' => "Aucune boutique n'est rattachée à ce compte.",
            ]);
        }

        $modification = $request->filled('id_product');

        $valide = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'id_category' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_init' => ['required', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            // L'image n'est exigée qu'à la création : une modification qui n'y
            // touche pas garde celle déjà en place.
            'image' => [$modification ? 'nullable' : 'required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $donnees = collect($valide)->except('image')->all();
        $donnees['quantity'] = $donnees['stock_init'];

        if ($request->hasFile('image')) {
            $nom = hexdec(uniqid()) . '.' . $request->file('image')->getClientOriginalExtension();

            try {
                $request->file('image')->move(public_path('upload'), $nom);
                /*
                 | img et product_image1 doivent désigner le même fichier : la
                 | vitrine lit l'un, l'application mobile l'autre. N'en remplir
                 | qu'un laisse le produit sans image d'un côté.
                 */
                $donnees['img'] = url('upload/' . $nom);
                $donnees['product_image1'] = $donnees['img'];
            } catch (\Throwable $e) {
                Log::warning('Image de produit non enregistrée : ' . $e->getMessage());
            }
        }

        if ($modification) {
            $produit = Product::where('id_shop', $boutique->id)
                ->find((int) $request->input('id_product'));

            if (! $produit) {
                return response()->json([
                    'response' => 404,
                    'message' => "Ce produit n'appartient pas à votre boutique.",
                ]);
            }

            $produit->update($donnees);

            return response()->json([
                'response' => 200,
                'message' => 'Produit modifié.',
                'data' => ['id' => $produit->id],
            ]);
        }

        $donnees['id_shop'] = $boutique->id;
        $donnees['ref'] = 'PROD-' . strtoupper(substr(uniqid(), -6));
        $donnees['slug'] = str($donnees['name'])->slug()->toString();
        // Un produit créé par un marchand attend la validation de l'équipe
        // avant d'apparaître au catalogue.
        $donnees['status'] = 'pending';

        $produit = Product::create($donnees);

        return response()->json([
            'response' => 200,
            'message' => 'Produit créé. Il sera visible après validation par Poulet AFC.',
            'data' => ['id' => $produit->id],
        ]);
    }

    private function commandesDe(int $idBoutique)
    {
        return order_detail::whereHas(
            'carts.cart_items.product',
            fn ($q) => $q->where('id_shop', $idBoutique),
        );
    }
}
