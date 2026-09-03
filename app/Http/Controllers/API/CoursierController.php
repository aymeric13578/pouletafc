<?php

namespace App\Http\Controllers\API;

use App\Fonction\Fonction;
use App\Http\Controllers\Controller;
use App\Models\order_detail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Demande de course coursier depuis l'application cliente.
 *
 * L'écran existe et poste vers storeDeliveryOrder depuis des mois, mais cette
 * route n'a jamais été écrite : le serveur répondait « méthode non autorisée » et
 * l'écran restait bloqué sur son envoi. Aucune demande de coursier n'a donc
 * jamais abouti depuis l'application.
 *
 * Une course coursier est enregistrée comme une commande, à la différence près
 * qu'elle ne porte pas de panier : le client dépose un colis à un point et le
 * fait remettre à un autre. D'où id_cart nul et un prix qui n'est que la course.
 */
class CoursierController extends Controller
{
    /** Taille maximale d'une photo de colis, en kilooctets. */
    private const PHOTO_MAX_KO = 6144;

    public function storeDeliveryOrder(Request $request): JsonResponse
    {
        $this->exigerReponseJson($request);

        $valide = $request->validate([
            'id_user' => ['required'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'latDestination' => ['required', 'numeric'],
            'lonDestination' => ['required', 'numeric'],
            'address' => ['required', 'string', 'max:255'],
            'depart' => ['nullable', 'string', 'max:255'],
            // min:1, pas min:0 — une course coursier à 0 FCFA n'a jamais de
            // sens, et laissait passer un prix nul soumis par erreur ou
            // intentionnellement modifié côté client.
            'price' => ['required', 'numeric', 'min:1'],
            // Distance routière du colis (km). Optionnelle pour ne pas casser
            // les builds déjà installés ; dès qu'elle est là, le serveur
            // recalcule le prix et ignore celui du client.
            'distance_km' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'delivery_fees' => ['nullable', 'numeric', 'min:0'],
            'phone_customer' => ['nullable', 'string', 'max:20'],
            // Nom du destinataire, désormais dans son propre champ plutôt
            // qu'enfoui dans le texte libre `note`.
            'name_customer' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:' . self::PHOTO_MAX_KO],
        ]);

        // Voir App\Support\Tarification::prixRetenu : prix serveur si distance
        // fournie, prix client (déjà validé ≥ 1 ci-dessus) sinon.
        $prix = app(\App\Support\Tarification::class)->prixRetenu(
            \App\Models\Tarif::COURSIER,
            $valide['price'],
            $valide['distance_km'] ?? null,
        );

        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
        } while (order_detail::where('ref', $ref)->exists());

        $image = $this->enregistrerPhoto($request);

        try {
            $commande = order_detail::create([
                'id_user' => $valide['id_user'],
                // Une course coursier n'a pas de panier : le client fait porter
                // un colis, il n'achète rien à une boutique.
                'id_cart' => null,
                'qty' => 1,
                'ref' => $ref,
                'status' => 'pending',
                'price' => (float) $prix,
                'panier_price' => 0,
                'delivery_fees' => (int) ($valide['delivery_fees'] ?? 0),

                // Départ : d'où part le colis. C'est le rôle que tient la
                // boutique sur une commande ordinaire, d'où les colonnes latShop.
                'latShop' => (float) $valide['latitude'],
                'lonShop' => (float) $valide['longitude'],
                'depart' => $valide['depart'] ?? null,
                // shop_name n'accepte pas la valeur nulle en base.
                'shop_name' => $request->input('shop_name') ?: ($valide['depart'] ?? 'Point de départ'),
                'shop_id' => $request->input('shop_id') ?: null,

                // Arrivée : où le colis doit être remis.
                'latitude' => (float) $valide['latDestination'],
                'longitude' => (float) $valide['lonDestination'],
                'address' => $valide['address'],

                'phone_customer' => $this->numeroEntier($valide['phone_customer'] ?? null),
                'name_customer' => $valide['name_customer'] ?? null,
                'delivery_type' => $valide['delivery_type'] ?? 'coursier',
                /*
                 | payment_method est un enum('LIVRAISON','MOMO','OM') et non un
                 | texte libre. L'application envoie « ESPECES », que MySQL
                 | refuse : l'insertion échouait et la demande était perdue.
                 */
                'payment_method' => $this->moyenDePaiement($request->input('payment_method')),
                'reception_mode' => 'LIVRAISON',
                'delivery_code' => $request->input('delivery_code') ?: (string) rand(1000, 9999),
                /*
                 | L'application envoie « unpaid », que la colonne refuse : c'est
                 | un enum('pending','Success','failed'). Non traduit, l'insertion
                 | échouait en base et la demande était perdue.
                 */
                'status_paiement' => $this->statutPaiement($request->input('status_paiement')),
                'note' => $valide['note'] ?? null,
                'image' => $image,
                'date' => $request->input('date'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Création de course coursier impossible : ' . $e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => "La demande n'a pas pu être enregistrée.",
                'data' => null,
            ], 500);
        }

        return response()->json([
            // L'écran attend « code », et considère 100 comme un succès.
            'code' => 100,
            'message' => 'Demande enregistrée.',
            'data' => [
                'id' => $commande->id,
                'ref' => $commande->ref,
                'delivery_code' => $commande->delivery_code,
                'status' => $commande->status,
                'depart' => $commande->depart,
                'image_url' => $image ? url('upload/' . $image) : null,
            ],
        ]);
    }

    /**
     * Demandes de coursier en attente, pour l'application agent.
     *
     * Mêmes critères d'« en attente » que getAllWithoutSellerOrder
     * (id_agent nul, statuts waiting/want/take/process, journée en cours) —
     * seul le filtre delivery_type='coursier' + id_cart nul change, pour
     * isoler les demandes de coursier des commandes boutique ordinaires qui
     * partagent la même table.
     */
    public function getPendingCoursierRequests(Request $request): JsonResponse
    {
        $debut = now()->setTimezone('Africa/Douala')->startOfDay();

        // Colonnes explicitement listées sur 'user' : cette route v1.0 n'a
        // aucune authentification (CLAUDE.md règle 8), et un with('user')
        // sans restriction renvoyait en clair confirmation_code (le code de
        // réinitialisation de mot de passe, voir UserController::changePasswordByOtp)
        // ainsi que l'email et d'autres champs personnels du client — même
        // correctif que OrderController::getAllOrder().
        $demandes = order_detail::where('id_agent', null)
            ->where('delivery_type', 'coursier')
            ->whereNull('id_cart')
            ->whereIn('status', ['waiting', 'want', 'take', 'process', 'pending'])
            ->whereBetween('created_at', [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()])
            ->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])
            ->orderByDesc('id')
            ->get();

        return response()->json(['response' => 200, 'data' => $demandes]);
    }

    /**
     * Range la photo du colis à côté des autres images du site.
     *
     * Même dossier public/upload que les produits, les catégories et les pièces
     * des agents : le tableau de bord y sert déjà les images sans configuration
     * supplémentaire.
     */
    private function enregistrerPhoto(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        /*
         | extension() (devinée depuis le contenu réel du fichier) et non
         | getClientOriginalExtension() (le nom de fichier envoyé par le
         | client, arbitraire). Cette route v1.0 n'a aucune authentification
         | (CLAUDE.md règle 8) : un fichier au contenu image valide mais nommé
         | "x.php" côté client passait la validation 'image' puis était rangé
         | tel quel dans public/upload, exécutable par le serveur si son
         | contenu embarquait aussi du PHP (polyglotte image/PHP connu). Même
         | correctif appliqué à tous les points d'upload de l'application
         | (Admin/Merchand/API) — voir TASKS.md.
         */
        $fichier = $request->file('image');
        $nom = uniqid('colis_', true) . '.' . $fichier->extension();

        try {
            $fichier->move(public_path('upload'), $nom);
        } catch (\Throwable $e) {
            // Une photo qu'on ne sait pas ranger ne doit pas faire perdre la
            // demande : le colis part sans image plutôt que pas du tout.
            Log::warning('Photo de colis non enregistrée : ' . $e->getMessage());

            return null;
        }

        return $nom;
    }

    /**
     * phone_customer est un int(11) en base : un numéro camerounais y tient,
     * mais pas un numéro préfixé de son indicatif, qui déborderait et serait
     * tronqué silencieusement.
     */
    private function numeroEntier(?string $numero): ?int
    {
        if (! $numero) {
            return null;
        }

        $local = (new Fonction())->numeroLocal($numero);

        return $local !== '' ? (int) $local : null;
    }

    /**
     * Moyen de paiement ramené aux valeurs que la colonne accepte.
     *
     * « LIVRAISON » signifie ici « réglé à la remise », c'est-à-dire en espèces :
     * c'est le repli naturel, et de loin le cas le plus fréquent — 225 des 231
     * commandes en base le portent.
     *
     * @return 'LIVRAISON'|'MOMO'|'OM'
     */
    private function moyenDePaiement($valeur): string
    {
        return match (strtoupper((string) $valeur)) {
            'MOMO', 'MTN', 'MTN_MOMO' => 'MOMO',
            'OM', 'ORANGE', 'ORANGE_MONEY' => 'OM',
            default => 'LIVRAISON',
        };
    }

    /** @return 'pending'|'Success'|'failed' */
    private function statutPaiement($valeur): string
    {
        return match ((string) $valeur) {
            'Success', 'paid', 'paye' => 'Success',
            'failed', 'echec' => 'failed',
            default => 'pending',
        };
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
