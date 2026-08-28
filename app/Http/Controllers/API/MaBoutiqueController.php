<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Shop;
use App\Support\AnnulationDeCommande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Espace boutique dans l'application, pendant du module « Ma boutique » du
 * tableau de bord.
 *
 * Un marchand qui se connecte depuis son téléphone n'avait aucun moyen de voir
 * sa vitrine ni ses commandes : il fallait ouvrir le tableau de bord sur un
 * ordinateur. Ces endpoints lui rendent depuis l'application ce qu'il y trouve.
 *
 * Le rattachement passe par shops.id_user, exactement comme sur le tableau de
 * bord. Toutes les requêtes repartent de la boutique du titulaire du jeton
 * envoyé, jamais d'un id_user transmis en paramètre : un id_user est un
 * entier public et devinable, et l'API v1.0 n'a par ailleurs aucune
 * authentification (CLAUDE.md règle 8) — le faire résoudre directement
 * permettrait à n'importe qui de lire et modifier la boutique d'un autre en
 * changeant ce seul paramètre. Le jeton (Sanctum, émis par
 * UserController::login au moment de la connexion, donc après vérification
 * du mot de passe) est la seule preuve d'identité que ce contrôleur accepte.
 */
class MaBoutiqueController extends Controller
{
    /**
     * Boutique du titulaire du jeton envoyé par l'appelant (champ `token`),
     * ou null si le jeton est absent/invalide/ne correspond à aucune
     * boutique. Ignore délibérément tout id_user fourni en paramètre — voir
     * le docblock de la classe.
     */
    private function boutiqueVerifiee(Request $request): ?Shop
    {
        $jeton = PersonalAccessToken::findToken((string) $request->input('token'));

        if (! $jeton || ! $jeton->tokenable) {
            return null;
        }

        return Shop::where('id_user', $jeton->tokenable_id)->first();
    }

    /**
     * Ce que l'application demande juste après la connexion pour savoir s'il
     * faut proposer l'entrée « Ma boutique ».
     */
    public function getMyShop(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

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
                'logo' => $this->urlImage($boutique->logo),
                'banner' => $this->urlImage($boutique->banner),
                'opening_hours' => $boutique->opening_hours,
                'is_open_now' => $boutique->estOuverteMaintenant(),
                'stats' => [
                    'produits' => (clone $produits)->count(),
                    'commandes' => $this->commandesDe($boutique->id)->count(),
                    'en_cours' => $this->commandesDe($boutique->id)
                        ->whereIn('status', ['pending', 'want', 'take', 'process'])
                        ->count(),
                    /*
                     | Les trois compteurs que l'espace marchand du tableau de
                     | bord affiche déjà. Le marchand doit lire la même chose sur
                     | son téléphone et sur son ordinateur : deux comptes
                     | différents pour la même boutique jettent le doute sur les
                     | deux. Ils voyagent avec la boutique plutôt que dans un
                     | second appel, l'écran n'ayant rien à afficher sans elle.
                     */
                    'a_valider' => (clone $produits)->where('status', 'pending')->count(),
                    'stock_faible' => (clone $produits)->where('stock_init', '<', 10)->count(),
                    'valeur_stock' => (int) (clone $produits)->sum(\DB::raw('price * stock_init')),
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
        $this->exigerReponseJson($request);

        $boutique = $this->boutiqueVerifiee($request);

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
            'banner' => ['nullable', 'image', 'max:4096'],
            // Envoyé en JSON par une requête multipart (les fichiers logo/
            // banner l'imposent) : {"1": {"closed": false, "opens_at":
            // "08:00", "closes_at": "20:00"}, ..., "7": {...}}.
            'opening_hours' => ['nullable', 'json'],
        ]);

        if ($request->filled('opening_hours')) {
            $valide['opening_hours'] = json_decode($valide['opening_hours'], true) ?? [];
        }

        // getAllshops (vitrine client) renvoie logo/banner tels quels, sans
        // les préfixer — contrairement à getMyShop/verifiedShopUser plus
        // bas, qui préfixaient eux-mêmes un nom de fichier brut. Stocker
        // l'URL complète ici, comme le fait déjà saveMyShopProduct pour les
        // photos de produit, évite ce genre d'image invisible côté client
        // alors que « Ma boutique » l'affichait très bien.
        foreach (['logo', 'banner'] as $champ) {
            if (! $request->hasFile($champ)) {
                unset($valide[$champ]);
                continue;
            }

            $nom = uniqid($champ . '_', true) . '.' . $request->file($champ)->getClientOriginalExtension();

            try {
                $request->file($champ)->move(public_path('upload'), $nom);
                $valide[$champ] = url('upload/' . $nom);
            } catch (\Throwable $e) {
                Log::warning(ucfirst($champ) . ' de boutique non enregistré : ' . $e->getMessage());
                unset($valide[$champ]);
            }
        }

        $boutique->update($valide);

        return response()->json([
            'response' => 200,
            'message' => 'Boutique mise à jour.',
            // Sans ça, l'écran ne pouvait afficher le nouveau logo/bannière
            // qu'après avoir rouvert « Ma boutique » depuis le menu — ce
            // second appel refaisait le même travail que celui-ci vient de
            // faire.
            'data' => [
                'logo' => $this->urlImage($boutique->logo),
                'banner' => $this->urlImage($boutique->banner),
                'opening_hours' => $boutique->fresh()->opening_hours,
            ],
        ]);
    }

    /** Produits de la boutique de l'appelant. */
    public function getMyShopProducts(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

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
        $boutique = $this->boutiqueVerifiee($request);

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
     * Vérifie si ce compte tient une boutique — format attendu par l'onglet
     * « Boutiques » de l'application.
     *
     * Cet écran appelle verifiedShopUser depuis toujours, et la route n'a jamais
     * existé : l'appel répondait 404, l'application tombait dans sa branche
     * d'erreur et affichait « Vous ne possédez pas encore de boutique » à tous
     * les marchands, y compris à ceux qui en tiennent une.
     *
     * La forme de la réponse est celle que l'écran sait lire, et elle est
     * inhabituelle : « code » à 100 pour un succès, et « message » contenant la
     * liste des boutiques sous forme de chaîne JSON, que l'application décode
     * elle-même. On s'y conforme plutôt que de toucher au client déjà installé.
     */
    public function verifiedShopUser(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json([
                'code' => 404,
                'message' => '[]',
            ]);
        }

        return response()->json([
            'code' => 100,
            'message' => json_encode([[
                'id' => $boutique->id,
                'shop_name' => $boutique->shop_name,
                'ref' => $boutique->ref,
                'city' => $boutique->city,
                'address' => $boutique->address,
                'phone1' => $boutique->phone1,
                'phone2' => $boutique->phone2,
                'email1' => $boutique->email1,
                'description' => $boutique->description,
                'status' => $boutique->status,
                'logo' => $this->urlImage($boutique->logo),
                'banner' => $this->urlImage($boutique->banner),
                'opening_hours' => $boutique->opening_hours,
                'is_open_now' => $boutique->estOuverteMaintenant(),
                'product_count' => Product::where('id_shop', $boutique->id)->count(),
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Compteurs de la boutique, pour l'écran de gestion.
     *
     * Même sort que verifiedShopUser : l'écran interroge cette route, qui
     * n'existait pas. Les compteurs restaient donc à zéro même sur une boutique
     * active. Elle attend « response » à 100, et non 200.
     */
    public function getShopStats(Request $request): JsonResponse
    {
        /*
         | Deux appelants, deux identifiants : l'écran boutique de l'application
         | connaît l'utilisateur connecté, la vitrine publique connaît la
         | boutique. Accepter les deux évite à l'appelant un aller-retour
         | supplémentaire juste pour traduire l'un en l'autre.
         */
        $idBoutique = (int) $request->input('shop_id');

        if (! $idBoutique && $request->filled('id_user')) {
            $idBoutique = (int) Shop::where('id_user', (int) $request->input('id_user'))->value('id');
        }

        if (! $idBoutique || ! Shop::whereKey($idBoutique)->exists()) {
            return response()->json(['response' => 404, 'data' => null]);
        }

        $produits = Product::where('id_shop', $idBoutique);

        return response()->json([
            'response' => 100,
            'data' => [
                'nombre_produits' => (clone $produits)->count(),
                'nombre_commandes' => $this->commandesDe($idBoutique)->count(),
                'commandes_en_attente' => $this->commandesDe($idBoutique)
                    ->whereIn('status', ['pending', 'want', 'take', 'process'])
                    ->count(),
                /*
                 | Les trois compteurs que l'espace marchand du tableau de bord
                 | affiche déjà. Le marchand doit lire la même chose sur son
                 | téléphone et sur son ordinateur : deux comptes différents pour
                 | la même boutique jettent le doute sur les deux.
                 */
                'produits_en_attente' => (clone $produits)->where('status', 'pending')->count(),
                'stock_faible' => (clone $produits)->where('stock_init', '<', 10)->count(),
                'valeur_stock' => (int) (clone $produits)->sum(\DB::raw('price * stock_init')),
            ],
        ]);
    }

    /**
     * Chiffre d'affaires de la boutique de l'appelant.
     *
     * Une commande peut mêler plusieurs boutiques (voir commandesDe) : le
     * montant retenu ici n'est jamais order_detail.price (le total de TOUTE
     * la commande, articles d'autres boutiques compris) mais la somme des
     * lignes de panier qui appartiennent réellement à cette boutique —
     * cart_items.amount (prix unitaire déjà remisé si une promotion était
     * active, voir PanierValideController) × quantity.
     *
     * status='declin'/'failed' est exclu (jamais devenu une vente) ; parmi
     * ce qui reste, seul status='Success' compte dans le chiffre d'affaires
     * — le reste (pending/waiting/want/take/process) est une commande en
     * cours, pas encore une vente, et apparaît séparément en
     * montant_en_attente.
     */
    public function getMyShopFinance(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json(['response' => 404, 'data' => null]);
        }

        $commandes = $this->commandesDe($boutique->id)
            ->whereNotIn('status', ['declin', 'failed'])
            ->with('carts.cart_items.product:id,id_shop')
            ->get(['id', 'ref', 'status', 'id_cart', 'created_at']);

        $maintenant = now()->setTimezone('Africa/Douala');
        $debutJour = $maintenant->copy()->startOfDay();
        $septJours = $maintenant->copy()->subDays(7);
        $debutMois = $maintenant->copy()->startOfMonth();

        $revenuTotal = 0.0;
        $revenuAujourdhui = 0.0;
        $revenuSemaine = 0.0;
        $revenuMois = 0.0;
        $montantEnAttente = 0.0;
        $commandesCompletees = 0;
        $transactions = [];

        foreach ($commandes as $commande) {
            $lignesBoutique = ($commande->carts?->cart_items ?? collect())
                ->filter(fn ($item) => $item->product?->id_shop === $boutique->id);

            $montant = (float) $lignesBoutique->sum(fn ($item) => $item->amount * $item->quantity);

            if ($montant <= 0) {
                continue;
            }

            if ($commande->status !== 'Success') {
                $montantEnAttente += $montant;

                continue;
            }

            $commandesCompletees++;
            $revenuTotal += $montant;

            $creeLe = $commande->created_at?->setTimezone('Africa/Douala');
            if ($creeLe?->greaterThanOrEqualTo($debutJour)) {
                $revenuAujourdhui += $montant;
            }
            if ($creeLe?->greaterThanOrEqualTo($septJours)) {
                $revenuSemaine += $montant;
            }
            if ($creeLe?->greaterThanOrEqualTo($debutMois)) {
                $revenuMois += $montant;
            }

            $transactions[] = [
                'ref' => $commande->ref,
                'montant' => round($montant, 2),
                'created_at' => $commande->created_at?->toIso8601String(),
            ];
        }

        usort($transactions, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return response()->json([
            'response' => 200,
            'data' => [
                'revenu_total' => round($revenuTotal, 2),
                'revenu_aujourdhui' => round($revenuAujourdhui, 2),
                'revenu_semaine' => round($revenuSemaine, 2),
                'revenu_mois' => round($revenuMois, 2),
                'montant_en_attente' => round($montantEnAttente, 2),
                'commandes_completees' => $commandesCompletees,
                'panier_moyen' => $commandesCompletees > 0
                    ? round($revenuTotal / $commandesCompletees, 2)
                    : 0,
                'transactions' => array_slice($transactions, 0, 20),
            ],
        ]);
    }

    /**
     * Demandes de coursier faites par la boutique de l'appelant, encore en
     * cours — voir CoursierController::storeDeliveryOrder (shop_id) et
     * DeliveryRequestScreen côté application, qui soumet ces demandes.
     *
     * Distinct de getMyShopOrders : une demande de coursier n'a pas de
     * panier (id_cart nul), elle n'apparaît donc jamais dans ce chemin-là,
     * qui ne remonte que via carts.cart_items.product.id_shop.
     */
    public function getMyShopDeliveryRequests(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json(['response' => 404, 'data' => []]);
        }

        /*
         | Pas de filtre sur delivery_type='coursier' : DeliveryRequestScreen
         | y met en réalité le type de colis choisi par le marchand
         | ("Document", "Fragile"...), jamais le mot "coursier" — un premier
         | essai avec ce filtre ne retournait donc jamais rien. Le vrai
         | discriminant, déjà utilisé par
         | CoursierController::getPendingCoursierRequests, est l'absence de
         | panier : une commande boutique classique en a toujours un, une
         | demande de coursier jamais.
         */
        $demandes = order_detail::where('shop_id', $boutique->id)
            ->whereNull('id_cart')
            ->whereIn('status', ['pending', 'waiting', 'want', 'take', 'process'])
            ->orderByDesc('id')
            ->get(['id', 'ref', 'status', 'price', 'address', 'depart', 'delivery_code', 'created_at']);

        return response()->json([
            'response' => 200,
            'data' => $demandes,
        ]);
    }

    /**
     * Annule une demande de coursier de la boutique de l'appelant.
     *
     * Réutilise AnnulationDeCommande::appliquer() — le même mécanisme que le
     * mur des commandes, la carte des clandos et l'application agent
     * (AnnulationController), pour que « annulé » ne soit jamais qu'à moitié
     * vrai selon qui a appuyé sur le bouton. AnnulationController::annuler()
     * n'est volontairement pas réutilisé tel quel : il retrouve la ligne par
     * son seul id, sans vérifier qu'elle appartient à l'appelant — ici, la
     * demande est cherchée directement où('shop_id', $boutique->id), comme
     * getMyShopDeliveryRequests, pour qu'un marchand ne puisse annuler que ses
     * propres demandes (l'API v1.0 n'a pas d'authentification, voir
     * CLAUDE.md règle 8).
     *
     * L'agent déjà assigné (id_agent) n'est pas averti par une notification
     * poussée ici : il le découvre au prochain rafraîchissement de son écran
     * de course (voir OrderScreenCommand.getOrderInfo côté application agent),
     * qui interroge déjà getOrder en boucle et lit désormais cancelled_by.
     */
    public function cancelMyShopDeliveryRequest(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json(['response' => 404, 'message' => "Aucune boutique n'est rattachée à ce compte."]);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        if (! AnnulationDeCommande::motifValide($motif)) {
            return response()->json(['response' => 400, 'message' => "Indiquez pourquoi cette livraison est annulée."]);
        }

        $demande = order_detail::where('id', (int) $request->input('id'))
            ->where('shop_id', $boutique->id)
            ->whereNull('id_cart')
            ->first();

        if (! $demande) {
            return response()->json(['response' => 404, 'message' => "Cette demande n'appartient pas à votre boutique."]);
        }

        if (in_array($demande->status, ['Success', 'failed', 'declin'], true)) {
            return response()->json([
                'response' => 409,
                'message' => $demande->status === 'Success'
                    ? 'Cette livraison a déjà été effectuée, elle ne peut plus être annulée.'
                    : 'Cette livraison est déjà terminée.',
            ]);
        }

        if (! AnnulationDeCommande::appliquer($demande, $motif, 'shop')) {
            return response()->json(['response' => 400, 'message' => "L'annulation n'a pas pu être enregistrée."]);
        }

        return response()->json([
            'response' => 200,
            'message' => 'Livraison annulée.',
            'motif' => AnnulationDeCommande::nettoyerLeMotif($motif),
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
        $this->exigerReponseJson($request);

        $boutique = $this->boutiqueVerifiee($request);

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

    /**
     * Promotions de la boutique de l'appelant, produit associé inclus (nom
     * et prix : l'écran en a besoin pour afficher le prix avant/après sans
     * un second appel).
     */
    public function getMyShopPromotions(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json(['response' => 404, 'data' => []]);
        }

        $promotions = Promotion::where('id_shop', $boutique->id)
            ->with('product:id,name,price,product_image1')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'response' => 200,
            'data' => $promotions->map(fn (Promotion $promo) => [
                'id' => $promo->id,
                'title' => $promo->title,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'starts_at' => $promo->starts_at?->toIso8601String(),
                'ends_at' => $promo->ends_at?->toIso8601String(),
                'status' => $promo->status,
                'id_product' => $promo->id_product,
                'product_name' => $promo->product?->name,
                'product_image' => $promo->product?->product_image1,
                'product_price' => (float) ($promo->product?->price ?? 0),
                'price_after' => $promo->product
                    ? round($promo->prixApres((float) $promo->product->price), 2)
                    : null,
            ])->values(),
        ]);
    }

    /**
     * Crée ou modifie une promotion de la boutique de l'appelant.
     *
     * Même garde que saveMyShopProduct : le produit ciblé est cherché par
     * where('id_shop')->find(), jamais par un id seul, pour qu'une remise ne
     * puisse jamais viser le produit d'une autre boutique.
     */
    public function saveMyShopPromotion(Request $request): JsonResponse
    {
        $this->exigerReponseJson($request);

        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json([
                'response' => 404,
                'message' => "Aucune boutique n'est rattachée à ce compte.",
            ]);
        }

        $modification = $request->filled('id_promotion');

        $valide = $request->validate([
            'id_product' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:191'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        if ($valide['discount_type'] === 'percentage' && $valide['discount_value'] > 100) {
            return response()->json([
                'response' => 422,
                'message' => 'Une remise en pourcentage ne peut pas dépasser 100.',
            ]);
        }

        $produit = Product::where('id_shop', $boutique->id)->find($valide['id_product']);

        if (! $produit) {
            return response()->json([
                'response' => 404,
                'message' => "Ce produit n'appartient pas à votre boutique.",
            ]);
        }

        if ($modification) {
            $promotion = Promotion::where('id_shop', $boutique->id)
                ->find((int) $request->input('id_promotion'));

            if (! $promotion) {
                return response()->json([
                    'response' => 404,
                    'message' => "Cette promotion n'appartient pas à votre boutique.",
                ]);
            }

            $promotion->update($valide);

            return response()->json([
                'response' => 200,
                'message' => 'Promotion modifiée.',
                'data' => ['id' => $promotion->id],
            ]);
        }

        $valide['id_shop'] = $boutique->id;
        // Comme un produit créé depuis ce même espace (saveMyShopProduct) :
        // en attente de validation avant d'apparaître aux clients.
        $valide['status'] = 'pending';

        $promotion = Promotion::create($valide);

        return response()->json([
            'response' => 200,
            'message' => 'Promotion créée. Elle sera visible après validation par Poulet AFC.',
            'data' => ['id' => $promotion->id],
        ]);
    }

    /** Retire une promotion de la boutique de l'appelant. */
    public function deleteMyShopPromotion(Request $request): JsonResponse
    {
        $boutique = $this->boutiqueVerifiee($request);

        if (! $boutique) {
            return response()->json([
                'response' => 404,
                'message' => "Aucune boutique n'est rattachée à ce compte.",
            ]);
        }

        $supprimee = Promotion::where('id_shop', $boutique->id)
            ->where('id', (int) $request->input('id_promotion'))
            ->delete();

        if (! $supprimee) {
            return response()->json([
                'response' => 404,
                'message' => "Cette promotion n'appartient pas à votre boutique.",
            ]);
        }

        return response()->json(['response' => 200, 'message' => 'Promotion supprimée.']);
    }

    /**
     * updateMyShop enregistre désormais l'URL complète (comme
     * saveMyShopProduct le fait déjà pour les photos de produit), mais des
     * boutiques éditées avant ce correctif — ou par un autre chemin
     * d'écriture — peuvent encore avoir un simple nom de fichier en base.
     * Préfixer seulement ce qui n'est pas déjà une URL absolue gère les deux
     * sans avoir à corriger les données existantes.
     */
    private function urlImage(?string $valeur): ?string
    {
        if (! $valeur) {
            return null;
        }

        return str_starts_with($valeur, 'http') ? $valeur : url('upload/' . $valeur);
    }

    private function commandesDe(int $idBoutique)
    {
        return order_detail::whereHas(
            'carts.cart_items.product',
            fn ($q) => $q->where('id_shop', $idBoutique),
        );
    }

    /**
     * Impose une réponse JSON, quel que soit l'en-tête Accept de l'appelant.
     *
     * L'application envoie ses formulaires avec MultipartRequest, qui ne pose
     * aucun en-tête Accept. Laravel en conclut qu'il parle à un navigateur et
     * répond à un échec de validation par une redirection : l'application
     * recevait du HTML, jsonDecode levait une exception, et l'utilisateur voyait
     * « erreur » sans jamais savoir quel champ était en cause.
     */
    private function exigerReponseJson(Request $request): void
    {
        $request->headers->set('Accept', 'application/json');
    }
}
