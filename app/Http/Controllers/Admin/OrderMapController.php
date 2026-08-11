<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use App\Models\User;
use App\Support\AttributionAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carte des livraisons : activité des agents et points de livraison.
 *
 * Pendant du mur des commandes, qui ne dit que « où en est cette commande » sans
 * jamais dire « où ». Le comptoir voyait une adresse en texte, sans moyen de
 * savoir quel agent en était le plus proche — d'où des livraisons confiées à
 * quelqu'un qui traversait la ville alors qu'un autre passait devant la porte.
 *
 * Trois points par commande, et ils ne se confondent pas :
 *  - latShop/lonShop : la boutique, d'où part le colis ;
 *  - latitude/longitude : le point de livraison, où va le colis ;
 *  - latAgent/lonAgent : l'agent, poussé pendant la course.
 *
 * Les positions d'agent suivent la même règle que sur la carte clando : en
 * livraison, elles viennent de la commande et sont rafraîchies en continu ; hors
 * livraison, c'est le dernier point posé au démarrage de journée, qui ne bouge
 * plus. La carte distingue les deux plutôt que de laisser un point figé passer
 * pour du direct.
 */
class OrderMapController extends Controller
{
    /** Statuts d'une commande encore en vie, donc à afficher sur la carte. */
    private const ACTIFS = ['pending', 'waiting', 'want', 'take', 'process'];

    /** Statuts d'une commande que personne n'a prise : c'est ce qui alerte. */
    private const EN_ATTENTE = ['pending', 'waiting', 'want'];

    private const STATUTS = [
        'pending' => 'En attente',
        // « Colis prêt » pose « want » : c'est le statut que guette la sonnerie
        // des agents. « waiting » n'est plus posé, son libellé sert aux lignes
        // qui le portent encore.
        'waiting' => 'Colis prêt (ancien)',
        'want' => 'Colis prêt',
        'process' => 'En cours',
        'take' => 'Prise en charge',
        'Success' => 'Livrée',
        'failed' => 'Échec',
    ];

    /*
     | Heure du Cameroun (WAT, UTC+1), converti à l'affichage seulement : les dates
     | sont stockées en UTC et basculer config('app.timezone') décalerait tout
     | l'historique d'une heure.
     */
    private const FUSEAU = 'Africa/Douala';

    /** Commandes achevées gardées sur la carte, pour lire l'activité récente. */
    private const RECENTES = 20;

    public function index(Request $request): Response
    {
        return Inertia::render('Orders/Map', [
            'initial' => $this->payload($request),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->payload($request))
            // La carte reste ouverte des heures : un flux mis en cache figerait
            // les agents sur une position périmée, ce qui trompe plus que rien.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Attribue une commande à un livreur depuis la carte.
     *
     * Règles reprises de takeOrderCommand, qui contrôle le solde contre le prix
     * de la commande — et non contre la commission comme le fait le clando.
     */
    public function assign(Request $request, int $order, AttributionAgent $attribution): JsonResponse
    {
        $valide = $request->validate([
            'id_agent' => ['required', 'integer'],
        ]);

        $commande = order_detail::findOrFail($order);

        $resultat = $attribution->attribuer($commande, $valide['id_agent'], (float) $commande->price);

        return response()->json($this->payload($request) + [
            'attribution' => ['ok' => $resultat['ok'], 'message' => $resultat['message']],
        ], $resultat['code'])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $commandes = $this->commandesAffichees();

        return [
            'orders' => $commandes->map(fn (order_detail $o) => $this->transform($o))->values(),
            'agents' => $this->agents($commandes),
            'agents_disponibles' => app(AttributionAgent::class)->agentsDisponibles(),
            'stats' => [
                'actives' => order_detail::whereIn('status', self::ACTIFS)->count(),
                'en_attente' => order_detail::whereIn('status', self::EN_ATTENTE)->count(),
                'du_jour' => order_detail::whereBetween('created_at', $this->bornesDuJour())->count(),
                'ca_jour' => (int) order_detail::whereBetween('created_at', $this->bornesDuJour())
                    ->where('status', 'Success')
                    ->sum('price'),
                'agents_actifs' => $this->requeteAgentsActifs()->count(),
            ],
            // Calculé sur toute la table, jamais sur ce qui est affiché : une
            // commande arrivée pendant qu'un filtre est actif doit alerter aussi.
            'latest_id' => (int) order_detail::max('id'),
            'server_time' => now()->setTimezone(self::FUSEAU)->format('H:i:s'),
            'server_date' => now()->setTimezone(self::FUSEAU)->locale('fr')->translatedFormat('l j F Y'),
        ];
    }

    /**
     * Commandes en cours, complétées de quelques commandes achevées.
     *
     * Les actives sont toutes prises, sans plafond : ce sont elles qui portent
     * les points mobiles, en omettre une reviendrait à perdre un agent de vue.
     *
     * @return Collection<int, order_detail>
     */
    private function commandesAffichees(): Collection
    {
        $relations = ['user:id,name,phone,whatsapp', 'agent.user:id,name,phone,whatsapp'];

        $actives = order_detail::with($relations)
            ->whereIn('status', self::ACTIFS)
            ->orderByDesc('id')
            ->get();

        $terminees = order_detail::with($relations)
            ->whereNotIn('status', self::ACTIFS)
            ->orderByDesc('id')
            ->limit(self::RECENTES)
            ->get();

        return $actives->concat($terminees);
    }

    /**
     * Agents en service, à leur position la plus fraîche.
     *
     * @param  Collection<int, order_detail>  $commandes
     * @return array<int, array<string, mixed>>
     */
    private function agents(Collection $commandes): array
    {
        $enLivraison = [];
        foreach ($commandes as $commande) {
            if (! in_array($commande->status, self::ACTIFS, true) || ! $commande->id_agent) {
                continue;
            }

            $lat = $this->nombre($commande->latAgent);
            $lon = $this->nombre($commande->lonAgent);

            if ($lat === null || $lon === null) {
                continue;
            }

            $enLivraison[$commande->id_agent] = [
                'lat' => $lat,
                'lon' => $lon,
                'commande_ref' => $commande->ref,
            ];
        }

        return $this->requeteAgentsActifs()
            ->get(['id', 'name', 'phone', 'whatsapp', 'actual_lat_position_agent', 'actual_lon_position_agent'])
            ->map(function (User $u) use ($enLivraison) {
                $suivi = $enLivraison[$u->id] ?? null;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'whatsapp' => $u->whatsapp,
                    'lat' => $suivi['lat'] ?? $this->nombre($u->actual_lat_position_agent),
                    'lon' => $suivi['lon'] ?? $this->nombre($u->actual_lon_position_agent),
                    'frais' => $suivi !== null,
                    'commande_ref' => $suivi['commande_ref'] ?? null,
                ];
            })
            ->filter(fn (array $a) => $a['lat'] !== null && $a['lon'] !== null)
            ->values()
            ->all();
    }

    private function requeteAgentsActifs()
    {
        return User::query()->where('role', 'agent')->where('in_activity', 1);
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function bornesDuJour(): array
    {
        $debut = now()->setTimezone(self::FUSEAU)->startOfDay();

        return [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()];
    }

    /**
     * Coordonnée exploitable, ou null.
     *
     * Les colonnes de position sont des float nullables. Restent donc null et le
     * zéro posé quand rien n'a été relevé : un zéro tombe au large du golfe de
     * Guinée et ferait dézoomer la carte sur l'Atlantique.
     */
    private function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        return $nombre === 0.0 ? null : $nombre;
    }

    /**
     * @return array{lat: float, lon: float}|null
     */
    private function point($lat, $lon): ?array
    {
        $lat = $this->nombre($lat);
        $lon = $this->nombre($lon);

        return ($lat === null || $lon === null) ? null : ['lat' => $lat, 'lon' => $lon];
    }

    private function libelleJour(?\Illuminate\Support\Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        $local = $date->copy()->setTimezone(self::FUSEAU);
        $aujourdhui = now()->setTimezone(self::FUSEAU)->startOfDay();

        return match (true) {
            $local->isSameDay($aujourdhui) => "Aujourd'hui",
            $local->isSameDay($aujourdhui->copy()->subDay()) => 'Hier',
            default => $local->format('d/m/Y'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(order_detail $order): array
    {
        return [
            'id' => $order->id,
            'ref' => $order->ref,
            'status' => $order->status,
            'status_label' => self::STATUTS[$order->status] ?? $order->status,
            'status_paiement' => $order->status_paiement,
            'active' => in_array($order->status, self::ACTIFS, true),
            'en_attente' => in_array($order->status, self::EN_ATTENTE, true),
            // Une commande déjà attribuée ne doit pas proposer de bouton
            // d'attribution : l'écran s'appuie là-dessus.
            'attribuable' => $order->id_agent === null,
            'price' => (int) $order->price,
            'address' => $order->address,
            'payment_method' => $order->payment_method,
            'boutique' => $this->point($order->latShop, $order->lonShop),
            'livraison' => $this->point($order->latitude, $order->longitude),
            'agent_position' => $this->point($order->latAgent, $order->lonAgent),
            'customer' => $order->user ? [
                'name' => $order->user->name,
                'phone' => $order->user->phone,
                'whatsapp' => $order->user->whatsapp,
            ] : null,
            'agent' => $order->agent?->user ? [
                'id' => $order->agent->user->id,
                'name' => $order->agent->user->name,
                'phone' => $order->agent->user->phone,
            ] : null,
            'created_at' => $order->created_at?->toIso8601String(),
            'created_label' => $order->created_at?->setTimezone(self::FUSEAU)->format('H:i'),
            'created_day' => $this->libelleJour($order->created_at),
        ];
    }
}
