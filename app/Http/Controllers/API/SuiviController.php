<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Où cet utilisateur doit-il être ramené ? »
 *
 * Quand l'application se ferme — coupure, appel entrant, retour malencontreux —
 * l'écran de suivi disparaît et rien ne le rouvre. Le client qui voyait son agent
 * approcher se retrouve à l'accueil sans aucun moyen d'y revenir, alors que la
 * course continue côté serveur. L'agent en course perd de même sa carte, et avec
 * elle l'envoi de sa position.
 *
 * Aucun endpoint existant ne pouvait répondre à cette question :
 *  - getActiveCommand cherche du travail à prendre (status « want » sans agent),
 *    pas ce qu'on est déjà en train de faire ;
 *  - getClandoAgent renvoie tout ce qui n'est pas « Success », donc aussi les
 *    courses refusées et échouées, sans dire quel écran rouvrir ;
 *  - historiqueClandoUser renvoie l'historique complet.
 *
 * Celui-ci renvoie une seule chose, la plus récente, avec tout ce que l'écran de
 * suivi attend pour être reconstruit à l'identique. Il répond aux deux
 * applications et aux deux rôles : on peut être le client qu'on vient chercher
 * ou l'agent qui va chercher.
 */
class SuiviController extends Controller
{
    /** Course clando encore en vie. */
    private const CLANDO_ACTIFS = ['pending', 'want', 'take', 'process'];

    /** Livraison encore en vie. */
    private const ORDER_ACTIFS = ['pending', 'waiting', 'want', 'take', 'process'];

    /**
     * Au-delà, on ne propose plus de reprendre.
     *
     * Une demande restée « want » depuis trois jours n'a pas été interrompue :
     * elle a été abandonnée, et personne ne l'a jamais close. Proposer d'y
     * revenir rouvrirait une carte sur une course morte, ce qui est pire que de
     * ne rien proposer. Douze heures couvent largement une journée de travail
     * interrompue.
     */
    private const HEURES_DE_VALIDITE = 12;

    public function getCourseEnCours(Request $request): JsonResponse
    {
        $idUser = $request->input('id_user');

        if (! $idUser) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant utilisateur manquant',
                'data' => null,
            ]);
        }

        $depuis = now()->subHours(self::HEURES_DE_VALIDITE);

        /*
         | L'agent passe avant le client.
         |
         | Un même compte peut être des deux côtés, et dans ce cas c'est le
         | travail en cours qui prime : un agent qui roule doit retrouver sa
         | carte, c'est elle qui renvoie sa position au serveur. Le ramener sur
         | une commande qu'il a passée comme client couperait ce suivi.
         */
        $reprise = $this->courseAgent($idUser, $depuis)
            ?? $this->livraisonAgent($idUser, $depuis)
            ?? $this->courseClient($idUser, $depuis)
            ?? $this->livraisonClient($idUser, $depuis);

        return response()->json([
            'response' => 200,
            // null est une réponse normale, pas une erreur : la plupart du temps
            // l'utilisateur n'a rien en cours.
            'data' => $reprise,
        ]);
    }

    /**
     * « Qu'est-ce qui a bougé sur mes commandes ? »
     *
     * getCourseEnCours répond à une autre question — « où me ramener ? » — et ne
     * renvoie donc qu'une seule chose, la plus récente, en faisant passer le rôle
     * d'agent avant celui de client. Pour prévenir un client qu'un agent vient de
     * prendre sa commande, il faut au contraire tout ce qu'il a en cours : sinon
     * une deuxième commande prise pendant qu'une première tourne encore ne
     * déclencherait jamais rien.
     *
     * L'application compare cette liste à ce qu'elle a déjà annoncé et sonne pour
     * ce qui vient d'être pris. Le serveur ne garde donc aucune trace de ce qui a
     * été notifié : c'est le téléphone qui sait ce qu'il a affiché, et lui seul.
     */
    public function getSuivisClient(Request $request): JsonResponse
    {
        $idUser = $request->input('id_user');

        if (! $idUser) {
            return response()->json([
                'response' => 400,
                'message' => 'Identifiant utilisateur manquant',
                'data' => [],
            ]);
        }

        $depuis = now()->subHours(self::HEURES_DE_VALIDITE);

        $courses = Clando::with('agent.user:id,name,phone,whatsapp,actual_lat_position_agent,actual_lon_position_agent,position_updated_at')
            ->where('id_user', $idUser)
            ->whereIn('status', self::CLANDO_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Clando $course) => $this->transformerClando($course, 'client'));

        $livraisons = order_detail::with('agent.user:id,name,phone,whatsapp,actual_lat_position_agent,actual_lon_position_agent,position_updated_at')
            ->where('id_user', $idUser)
            ->whereIn('status', self::ORDER_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->get()
            ->map(fn (order_detail $commande) => $this->transformerCommande($commande, 'client'));

        return response()->json([
            'response' => 200,
            // Liste vide et non erreur : la plupart du temps le client n'attend rien.
            'data' => $courses->concat($livraisons)->values(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function courseAgent($idUser, $depuis): ?array
    {
        $course = Clando::with('users:id,name,phone,whatsapp')
            ->where('id_agent', $idUser)
            ->whereIn('status', self::CLANDO_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->first();

        return $course ? $this->transformerClando($course, 'agent') : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function courseClient($idUser, $depuis): ?array
    {
        $course = Clando::with('agent.user:id,name,phone,whatsapp,actual_lat_position_agent,actual_lon_position_agent,position_updated_at')
            ->where('id_user', $idUser)
            ->whereIn('status', self::CLANDO_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->first();

        return $course ? $this->transformerClando($course, 'client') : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function livraisonAgent($idUser, $depuis): ?array
    {
        $commande = order_detail::with('user:id,name,phone,whatsapp')
            ->where('id_agent', $idUser)
            ->whereIn('status', self::ORDER_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->first();

        return $commande ? $this->transformerCommande($commande, 'agent') : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function livraisonClient($idUser, $depuis): ?array
    {
        $commande = order_detail::with('agent.user:id,name,phone,whatsapp,actual_lat_position_agent,actual_lon_position_agent,position_updated_at')
            ->where('id_user', $idUser)
            ->whereIn('status', self::ORDER_ACTIFS)
            ->where('created_at', '>=', $depuis)
            ->orderByDesc('id')
            ->first();

        return $commande ? $this->transformerCommande($commande, 'client') : null;
    }

    /**
     * Charge utile d'une course clando.
     *
     * Les champs portent les noms qu'attendent les écrans Flutter, pour qu'une
     * reprise reconstruise exactement l'écran quitté plutôt qu'une approximation.
     *
     * @return array<string, mixed>
     */
    private function transformerClando(Clando $course, string $role): array
    {
        return [
            'type' => 'clando',
            'role' => $role,
            'id' => $course->id,
            'ref' => $course->ref,
            'status' => $course->status,
            /*
             | Quel écran rouvrir.
             |
             | Tant qu'aucun agent n'a pris la course, le client doit revenir sur
             | l'écran d'attente, pas sur la carte de suivi : il n'y a encore
             | personne à suivre, et la carte afficherait un agent immobile à
             | l'origine des coordonnées.
             */
            'ecran' => $course->id_agent === null ? 'attente' : 'suivi',
            'lat' => $this->nombre($course->latMyPosition),
            'lon' => $this->nombre($course->lonMyPosition),
            'lat_destination' => $this->nombre($course->latDestination),
            'lon_destination' => $this->nombre($course->lonDestination),
            'destination' => $course->destinationName,
            // Les écrans attendent ces deux champs pour s'afficher avant même
            // d'avoir recalculé l'itinéraire.
            'distance' => (float) ($course->distance ?? 0),
            'times' => $course->times ?? '0 min',
            'price' => (float) $course->price,
            'prise_en_charge' => $course->destinationName ?? 'Point de départ',
            'vehicule' => $course->vehicule,
            'matricule' => $course->matricule_vehicule,
            'agent' => $this->agent($course->agent?->user),
            'client' => $course->users ? [
                'id' => $course->users->id,
                'name' => $course->users->name,
                'phone' => $course->users->phone,
                'whatsapp' => $course->users->whatsapp,
            ] : null,
            'created_at' => $course->created_at?->toIso8601String(),
        ];
    }

    /**
     * Charge utile d'une livraison.
     *
     * @return array<string, mixed>
     */
    private function transformerCommande(order_detail $commande, string $role): array
    {
        return [
            'type' => 'order',
            'role' => $role,
            'id' => $commande->id,
            'ref' => $commande->ref,
            'status' => $commande->status,
            'ecran' => $commande->id_agent === null ? 'attente' : 'suivi',
            // Pour une livraison, le point de départ est la boutique et non le
            // client : c'est de là que part l'agent.
            'lat' => $this->nombre($commande->latShop),
            'lon' => $this->nombre($commande->lonShop),
            'lat_destination' => $this->nombre($commande->latitude),
            'lon_destination' => $this->nombre($commande->longitude),
            'destination' => $commande->address ?? 'Destination inconnue',
            // order_details ne stocke ni distance ni durée : les écrans les
            // recalculent depuis l'itinéraire, comme ils le font déjà à
            // l'ouverture normale.
            'distance' => 0.0,
            'times' => '0 min',
            'price' => (float) $commande->price,
            'prise_en_charge' => $commande->shop_name ?? 'Point de départ inconnu',
            'agent' => $this->agent($commande->agent?->user),
            'client' => $commande->user ? [
                'id' => $commande->user->id,
                'name' => $commande->user->name,
                'phone' => $commande->user->phone,
                'whatsapp' => $commande->user->whatsapp,
            ] : null,
            'created_at' => $commande->created_at?->toIso8601String(),
        ];
    }

    /**
     * Fiche de l'agent, position comprise.
     *
     * La position voyage avec le nom : sans elle, l'écran de suivi ouvert depuis
     * une notification afficherait un agent nommé mais introuvable sur la carte,
     * le temps d'un second appel.
     *
     * @return array<string, mixed>|null
     */
    private function agent(?\App\Models\User $agent): ?array
    {
        if (! $agent) {
            return null;
        }

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'phone' => $agent->phone,
            'whatsapp' => $agent->whatsapp,
            'lat' => $this->nombre($agent->actual_lat_position_agent),
            'lon' => $this->nombre($agent->actual_lon_position_agent),
            /*
             | Une position vieille de plusieurs heures est pire qu'aucune : elle
             | montre l'agent figé loin de là où il est. L'écran affiche le point
             | seulement si la date le permet.
             */
            'position_datee' => $agent->position_updated_at?->toIso8601String(),
        ];
    }

    /**
     * Coordonnée exploitable, ou null.
     *
     * Un zéro tombe au large du golfe de Guinée : transmis tel quel, il ferait
     * ouvrir la carte à mille kilomètres de Douala.
     */
    private function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        return $nombre === 0.0 ? null : $nombre;
    }
}
