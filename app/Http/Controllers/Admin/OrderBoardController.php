<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mur des commandes affiché en continu sur un écran de télévision.
 *
 * Contrainte de départ : la page tourne sans personne devant le clavier, parfois
 * pendant des jours. Elle doit donc rester lisible à plusieurs mètres, ne jamais
 * recharger entièrement (le rechargement coupe le son et perd l'état), et
 * survivre à une coupure réseau sans se figer sur des données périmées.
 *
 * D'où la séparation en deux points d'entrée : le rendu Inertia initial, puis un
 * flux JSON léger interrogé en boucle. L'ancienne page rechargeait tout le
 * composant Livewire toutes les 5 secondes, ce qui reconstruisait le tableau
 * entier et interrompait les animations.
 */
class OrderBoardController extends Controller
{
    /** Commandes par page. Au-delà, l'écran devient illisible de loin. */
    private const PAR_PAGE = 12;

    /** Statuts considérés comme « à traiter ». */
    private const ACTIFS = ['pending', 'process', 'want', 'take'];

    /** Statuts qui déclenchent et entretiennent la sonnerie. */
    private const EN_ATTENTE = ['pending', 'want'];

    public function index(Request $request): Response
    {
        return Inertia::render('Orders/Board', [
            'initial' => $this->payload($request),
        ]);
    }

    /**
     * Flux interrogé en boucle par le navigateur. Volontairement minimal : il ne
     * renvoie que ce qui s'affiche, pour rester léger même à une requête toutes
     * les cinq secondes pendant des heures.
     */
    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->payload($request))
            // L'écran reste allumé des jours : un proxy ou le navigateur qui
            // mettrait ce flux en cache figerait le mur sur d'anciennes commandes.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));

        $commandes = order_detail::with([
                'user:id,name,phone,whatsapp,email',
                'agent.user:id,name,phone,whatsapp',
                'carts.cart_items.product:id,name,price',
            ])
            /*
             | Tri par urgence avant tri par date : ce qui attend une action monte en
             | haut de l'écran, ce qui est terminé descend en bas. Sur un mur consulté
             | d'un coup d'œil, l'ordre chronologique seul noyait les commandes à
             | traiter au milieu de commandes déjà livrées.
             */
            ->orderByRaw("CASE
                WHEN status IN ('pending', 'want') THEN 0
                WHEN status IN ('process', 'take') THEN 1
                WHEN status = 'Success' THEN 2
                ELSE 3
            END")
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE, ['*'], 'page', $page);

        return [
            'orders' => $commandes->getCollection()->map(fn (order_detail $o) => $this->transform($o))->values(),
            'pagination' => [
                'current_page' => $commandes->currentPage(),
                'last_page' => $commandes->lastPage(),
                'total' => $commandes->total(),
                'from' => $commandes->firstItem(),
                'to' => $commandes->lastItem(),
            ],
            'stats' => [
                'total' => order_detail::count(),
                'actives' => order_detail::whereIn('status', self::ACTIFS)->count(),
                'en_attente' => order_detail::whereIn('status', self::EN_ATTENTE)->count(),
                'livrees' => order_detail::where('status', 'Success')->count(),
                'ca_jour' => (int) order_detail::whereDate('created_at', today())
                    ->where('status', 'Success')
                    ->sum('price'),
                'du_jour' => order_detail::whereDate('created_at', today())->count(),
            ],
            /*
             | Identifiant le plus élevé, toutes pages confondues. La détection d'une
             | nouvelle commande ne peut pas se fonder sur la page affichée : si
             | quelqu'un consulte la page 3, la commande qui vient d'arriver n'y est
             | pas et la sonnerie ne partirait jamais.
             */
            'latest_id' => (int) order_detail::max('id'),
            // Horodatage serveur : le navigateur d'une télé a rarement une heure
            // juste, et l'écart se voit tout de suite sur un affichage permanent.
            'server_time' => now()->format('H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(order_detail $order): array
    {
        $items = $order->carts?->cart_items ?? collect();

        return [
            'id' => $order->id,
            'ref' => $order->ref,
            'price' => (int) $order->price,
            'status' => $order->status,
            'address' => $order->address,
            'payment_method' => $order->payment_method,
            'customer' => $order->user?->name,
            'phone' => $order->user?->phone,
            'whatsapp' => $order->user?->whatsapp,
            'email' => $order->user?->email,
            'created_at' => $order->created_at?->toIso8601String(),
            'created_label' => $order->created_at?->format('H:i'),
            'created_full' => $order->created_at?->format('d/m/Y à H:i'),
            // Panier complet : le détail est consulté à la demande depuis le tableau,
            // et la liste est plafonnée à 12 commandes, donc le poids reste faible.
            'items' => $items->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Article',
                'quantity' => (int) ($item->quantity ?? 1),
                'unit_price' => (int) ($item->price ?? $item->product?->price ?? 0),
                'amount' => (int) ($item->amount ?? 0),
            ])->values(),
            'items_count' => $items->count(),
            'agent' => $order->agent?->user ? [
                'name' => $order->agent->user->name,
                'phone' => $order->agent->user->phone,
                'whatsapp' => $order->agent->user->whatsapp,
            ] : null,
        ];
    }
}
