<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\order_detail;
use App\Support\ComplementsProposes;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\Parameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Statuts qui déclenchent et entretiennent la sonnerie. « waiting » (colis
     * prêt) en fait partie : un colis prêt que personne n'enlève doit continuer
     * d'alerter.
     */
    private const EN_ATTENTE = ['pending', 'waiting', 'want'];

    /*
     | Heure du Cameroun (WAT, UTC+1).
     |
     | On convertit à l'affichage plutôt que de basculer config('app.timezone') :
     | les dates déjà en base ont été écrites en UTC, et changer le fuseau de
     | l'application les ferait relire comme des heures de Douala. Toutes les
     | commandes passées afficheraient alors une heure fausse, décalée d'une heure,
     | et le décalage se propagerait partout ailleurs dans le projet.
     */
    private const FUSEAU = 'Africa/Douala';

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
     * Statuts qu'un opérateur peut poser depuis le mur.
     *
     * « waiting » y figure de nouveau : c'est le statut que posait le bouton
     * « Colis prêt » pour signaler aux agents qu'une commande est prête à être
     * enlevée. Je l'avais retiré en le croyant inutilisé — aucune commande ne le
     * portait en base — sans voir qu'il servait précisément à déclencher
     * l'alerte sur leurs téléphones.
     */
    private const STATUTS_AUTORISES = ['pending', 'waiting', 'want', 'take', 'process', 'Success', 'failed'];

    /**
     * Change le statut d'une commande depuis le mur.
     *
     * La page est volontairement en accès libre : l'écran tourne sans session
     * ouverte. Cette action est donc elle aussi accessible sans authentification,
     * ce qui est un choix assumé et non un oubli.
     */
    /**
     * Statut de paiement. La colonne est un enum('pending','Success','failed') :
     * on s'y tient, plutôt que d'inventer 'paid'/'unpaid' comme le faisait le
     * tunnel de commande du site — valeurs que MySQL refusait.
     */
    private const PAIEMENTS_AUTORISES = ['pending', 'Success', 'failed'];

    /**
     * Marque une commande payée ou impayée depuis le mur.
     */
    public function updatePayment(Request $request, int $order): JsonResponse
    {
        $valide = $request->validate([
            'status_paiement' => ['required', 'string', Rule::in(self::PAIEMENTS_AUTORISES)],
        ]);

        $commande = order_detail::findOrFail($order);
        $commande->status_paiement = $valide['status_paiement'];
        $commande->save();

        return response()->json($this->payload($request))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * Corrige le panier d'une commande à partir d'un poids.
     *
     * Un poulet n'est pesé qu'à la préparation, et le montant commandé ne
     * correspond presque jamais au poids réel. Jusqu'ici la commande restait
     * figée sur le prix annoncé et l'écart se réglait de la main à la main, sans
     * trace. Le comptoir saisit désormais le poids, le montant suit.
     *
     * Le tarif au kilo vient de la grille active, jamais du navigateur : sinon
     * n'importe qui atteignant cette page — elle est en accès libre — fixerait
     * le prix qu'il veut.
     */
    public function updateCart(Request $request, int $order): JsonResponse
    {
        $valide = $request->validate([
            // Trois décimales : on pèse au gramme près, mais un poids nul ou
            // négatif n'a pas de sens et viderait la commande.
            'poids_kg' => ['required', 'numeric', 'min:0.001', 'max:1000'],
        ]);

        $commande = order_detail::findOrFail($order);

        if (in_array($commande->status, ['Success', 'failed'], true)) {
            return $this->reponseAvec($request, false, 'Cette commande est close, son montant ne peut plus changer.', 409);
        }

        $tarif = Parameter::active()?->price_per_kg;

        if (! $tarif) {
            return $this->reponseAvec(
                $request,
                false,
                'Aucun prix au kilo n\'est réglé. Renseignez-le dans Configuration.',
                422,
            );
        }

        $poids = (float) $valide['poids_kg'];
        $panier = (int) round($poids * $tarif);

        $commande->update([
            'poids_kg' => $poids,
            'panier_price' => $panier,
            // Le total suit la même règle qu'à la création de la commande :
            // panier + frais de livraison.
            'price' => $panier + (int) $commande->delivery_fees,
        ]);

        /*
         | La commission de l'agent n'est délibérément pas recalculée.
         |
         | Elle a été figée à la création à partir du prix d'alors. La corriger
         | ici modifierait ce qu'un agent touche sur une course qu'il a peut-être
         | déjà acceptée, ce qui est une décision commerciale et non technique.
         | À trancher avec l'exploitation si l'écart devient gênant.
         */

        return $this->reponseAvec(
            $request,
            true,
            sprintf('Panier mis à jour : %s kg × %s F = %s F.',
                rtrim(rtrim(number_format($poids, 3, ',', ' '), '0'), ','),
                number_format($tarif, 0, ',', ' '),
                number_format($panier, 0, ',', ' '),
            ),
            200,
        );
    }

    /**
     * Retire un article du panier d'une commande.
     *
     * Le comptoir n'avait aucun moyen de corriger une commande : un article en
     * rupture ou commandé par erreur obligeait à tout annuler et à refaire la
     * saisie, en perdant l'historique et l'agent déjà attribué.
     */
    public function retirerArticle(Request $request, int $order, int $item): JsonResponse
    {
        $commande = order_detail::with('carts')->findOrFail($order);

        if ($refus = $this->refusSiClose($request, $commande)) {
            return $refus;
        }

        $ligne = CartItem::where('cart_id', $commande->id_cart)->find($item);

        if (! $ligne) {
            return $this->reponseAvec($request, false, 'Cet article ne fait pas partie de cette commande.', 404);
        }

        $nom = $ligne->product?->name ?? 'Article';
        $ligne->delete();

        $this->recalculerLeTotal($commande);

        return $this->reponseAvec($request, true, sprintf('%s retiré de la commande.', $nom), 200);
    }

    /**
     * Ajoute un article au panier d'une commande.
     *
     * Sert aussi à ajouter un complément : un complément est un produit, et
     * n'appelle donc pas de traitement séparé.
     */
    public function ajouterArticle(Request $request, int $order): JsonResponse
    {
        $valide = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $commande = order_detail::with('carts')->findOrFail($order);

        if ($refus = $this->refusSiClose($request, $commande)) {
            return $refus;
        }

        if (! $commande->id_cart) {
            /*
             | Une course de coursier n'a pas de panier : elle transporte un
             | colis, elle ne vend rien. Y ajouter un article n'aurait aucun
             | sens et créerait une commande hybride que rien ne sait afficher.
             */
            return $this->reponseAvec($request, false, "Cette commande n'a pas de panier : c'est une course.", 422);
        }

        $produit = Product::findOrFail($valide['product_id']);
        $quantite = (int) ($valide['quantity'] ?? 1);

        $existante = CartItem::where('cart_id', $commande->id_cart)
            ->where('product_id', $produit->id)
            ->first();

        if ($existante) {
            // Le même article deux fois donne une seule ligne de quantité
            // double : deux lignes identiques se corrigent mal et s'additionnent
            // mal à l'œil.
            $existante->update(['quantity' => $existante->quantity + $quantite]);
        } else {
            CartItem::create([
                'cart_id' => $commande->id_cart,
                'product_id' => $produit->id,
                'quantity' => $quantite,
                // Le prix est figé ici, comme à l'ajout au panier par le client :
                // il ne doit pas suivre les changements de tarif ultérieurs.
                'amount' => (int) $produit->price,
            ]);
        }

        $this->recalculerLeTotal($commande);

        return $this->reponseAvec($request, true, sprintf('%s ajouté à la commande.', $produit->name), 200);
    }

    /**
     * Refuse toute correction sur une commande close.
     */
    private function refusSiClose(Request $request, order_detail $commande): ?JsonResponse
    {
        if (in_array($commande->status, ['Success', 'failed'], true)) {
            return $this->reponseAvec($request, false, 'Cette commande est close, son panier ne peut plus changer.', 409);
        }

        return null;
    }

    /**
     * Réaligne le montant de la commande sur son panier.
     *
     * Le total est recalculé depuis les lignes plutôt qu'ajusté du montant de
     * l'article touché : une commande déjà corrigée au poids, ou dont un tarif a
     * bougé, dériverait sinon un peu plus à chaque geste.
     */
    private function recalculerLeTotal(order_detail $commande): void
    {
        $panier = (int) CartItem::where('cart_id', $commande->id_cart)
            ->get()
            ->sum(fn (CartItem $ligne) => (int) $ligne->quantity * (int) $ligne->amount);

        $commande->update([
            'panier_price' => $panier,
            'price' => $panier + (int) $commande->delivery_fees,
            /*
             | Le poids saisi devient faux dès que le panier change : le laisser
             | ferait croire que le montant en découle encore. Le comptoir le
             | ressaisira s'il pèse à nouveau.
             */
            'poids_kg' => null,
        ]);
    }

    /**
     * Compléments à proposer pour le panier d'une commande.
     *
     * Le comptoir prend souvent la commande au téléphone : il doit pouvoir dire
     * « avec des frites ? » sans connaître par cœur ce qui accompagne quoi.
     */
    public function complementsProposes(Request $request, int $order): JsonResponse
    {
        $commande = order_detail::with('carts.cart_items')->findOrFail($order);

        $produits = $commande->carts?->cart_items?->pluck('product_id') ?? collect();

        return response()->json(
            app(ComplementsProposes::class)->charge($produits)
        );
    }

    /**
     * Réponse d'une action du mur : le mur à jour, plus le compte rendu du geste.
     *
     * Renvoyer le mur évite au navigateur d'enchaîner une seconde requête, et
     * surtout d'attendre les cinq secondes du cycle suivant — le bouton
     * semblerait sans effet.
     */
    private function reponseAvec(Request $request, bool $ok, string $message, int $code): JsonResponse
    {
        return response()->json($this->payload($request) + [
            'action' => ['ok' => $ok, 'message' => $message],
        ], $code)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function updateStatus(Request $request, int $order): JsonResponse
    {
        $valide = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUTS_AUTORISES)],
        ]);

        $commande = order_detail::findOrFail($order);
        $commande->status = $valide['status'];
        $commande->save();

        // On renvoie le mur à jour : le navigateur n'a pas à enchaîner une seconde
        // requête pour rafraîchir l'écran après l'action.
        return response()->json($this->payload($request))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $recherche = trim((string) $request->query('recherche', ''));

        $commandes = order_detail::with([
                'user:id,name,phone,whatsapp,email',
                'agent.user:id,name,phone,whatsapp',
                'carts.cart_items.product:id,name,price,id_shop',
                'carts.cart_items.product.shop:id,shop_name',
                // Présence d'une course de coursier : c'est ce qui distingue une
                // demande de coursier d'une commande ordinaire sur l'écran.
                'coursier:id,id_order,ref,status,destinationName,price',
            ])
            /*
             | Tri strictement chronologique, de la plus récente à la plus ancienne.
             |
             | Un tri par statut avait été essayé pour faire remonter les commandes à
             | traiter. Il rendait le mur inutilisable dès qu'on agissait dessus :
             | prendre une commande la faisait changer de groupe, donc chuter derrière
             | toutes les commandes en attente — plus d'une centaine — et disparaître
             | de l'écran. Une ligne sur laquelle on vient de cliquer doit rester là
             | où elle est.
             */
            /*
             | Recherche.
             |
             | Le mur affiche douze commandes par page ; retrouver une commande
             | citée au téléphone obligeait à parcourir la pagination à la main.
             | La recherche porte sur ce qu'un client donne spontanément : sa
             | référence, son nom, son numéro ou son adresse.
             |
             | Elle s'applique en base et non sur la page affichée : chercher
             | dans les douze commandes visibles n'aurait servi à rien.
             */
            ->when($recherche !== '', function ($q) use ($recherche) {
                $terme = '%' . $recherche . '%';

                $q->where(function ($sous) use ($terme) {
                    $sous->where('ref', 'like', $terme)
                        ->orWhere('address', 'like', $terme)
                        ->orWhere('delivery_code', 'like', $terme)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $terme)
                            ->orWhere('phone', 'like', $terme)
                            ->orWhere('whatsapp', 'like', $terme));
                });
            })
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE, ['*'], 'page', $page);

        /*
         | Appréciations des commandes affichées, en une requête.
         |
         | Les résoudre ligne par ligne dans transform() ajouterait une requête
         | par commande : sur une page de vingt, c'est vingt allers-retours pour
         | un détail qui tient en une jointure.
         */
        $this->appreciations = \App\Models\Note::whereIn('id_order', $commandes->getCollection()->pluck('id'))
            ->get()
            ->keyBy('id_order');

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
                /*
                 | Bornes de la journée camerounaise converties en UTC, puisque les
                 | dates sont stockées ainsi. Avec un simple whereDate(today()), les
                 | compteurs « du jour » basculaient à minuit UTC, soit une heure du
                 | matin à Douala : entre minuit et une heure, l'écran affichait
                 | encore le chiffre d'affaires de la veille.
                 */
                'ca_jour' => (int) order_detail::whereBetween('created_at', $this->bornesDuJour())
                    ->where('status', 'Success')
                    ->sum('price'),
                'du_jour' => order_detail::whereBetween('created_at', $this->bornesDuJour())->count(),
            ],
            /*
             | Identifiant le plus élevé, toutes pages confondues. La détection d'une
             | nouvelle commande ne peut pas se fonder sur la page affichée : si
             | quelqu'un consulte la page 3, la commande qui vient d'arriver n'y est
             | pas et la sonnerie ne partirait jamais.
             */
            // Tarif au kilo en vigueur, pour que l'écran affiche le montant
            // pendant la saisie du poids. null quand aucune grille ne le règle :
            // le mur masque alors la correction au poids.
            'price_per_kg' => Parameter::active()?->price_per_kg
                ? (int) Parameter::active()->price_per_kg
                : null,
            'latest_id' => (int) order_detail::max('id'),
            // Horodatage serveur, en heure du Cameroun : le navigateur d'une télé a
            // rarement une heure juste, et l'écart se voit tout de suite sur un
            // affichage permanent.
            'recherche' => $recherche,
            'server_time' => now()->setTimezone(self::FUSEAU)->format('H:i:s'),
            'server_date' => now()->setTimezone(self::FUSEAU)->locale('fr')->translatedFormat('l j F Y'),
        ];
    }

    /**
     * Début et fin de la journée camerounaise, exprimés en UTC pour interroger la
     * base, où les dates sont stockées dans ce fuseau.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function bornesDuJour(): array
    {
        $debut = now()->setTimezone(self::FUSEAU)->startOfDay();

        return [
            $debut->copy()->utc(),
            $debut->copy()->endOfDay()->utc(),
        ];
    }

    /**
     * Jour d'une commande, exprimé en heure du Cameroun.
     *
     * « Aujourd'hui » et « Hier » plutôt qu'une date : sur un mur regardé de loin,
     * ce sont les seuls repères qu'on lit sans réfléchir.
     */
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
    /**
     * Appréciations de la page courante, indexées par commande.
     *
     * @var \Illuminate\Support\Collection|null
     */
    private $appreciations = null;

    /**
     * @return array<string, mixed>|null
     */
    private function appreciation(int $idCommande): ?array
    {
        $note = $this->appreciations?->get($idCommande);

        if (! $note) {
            return null;
        }

        return [
            'note' => $note->note,
            'libelle' => \App\Support\NotationAgent::LIBELLES[$note->note] ?? $note->note,
            'emoji' => \App\Support\NotationAgent::EMOJIS[$note->note] ?? '',
            'commentaire' => $note->comment,
        ];
    }

    private function transform(order_detail $order): array
    {
        $items = $order->carts?->cart_items ?? collect();

        return [
            'id' => $order->id,
            'ref' => $order->ref,
            'price' => (int) $order->price,
            /*
             | Décomposition du total.
             |
             | price vaut panier_price + delivery_fees depuis la création de la
             | commande, mais l'écran n'affichait que la somme : impossible de
             | dire au comptoir ce qui relevait des articles et ce qui relevait
             | de la livraison, ni de vérifier un montant contesté.
             |
             | panier_price peut être nul sur les commandes anciennes, créées
             | avant que la colonne soit renseignée : on retombe alors sur la
             | soustraction plutôt que d'afficher zéro.
             */
            'panier_price' => (int) ($order->panier_price ?? max(0, $order->price - (int) $order->delivery_fees)),
            'delivery_fees' => (int) $order->delivery_fees,
            'poids_kg' => $order->poids_kg !== null ? (float) $order->poids_kg : null,
            // Une commande livrée ou annulée ne se corrige plus : le montant a
            // déjà été encaissé et la commission calculée dessus.
            'panier_modifiable' => ! in_array($order->status, ['Success', 'failed'], true),
            'status' => $order->status,
            'status_paiement' => $order->status_paiement,
            /*
             | Nature de la demande. Les gestes du comptoir ne sont pas les mêmes
             | selon qu'on prépare un panier ou qu'on remet un paquet.
             |
             | Deux signes, car il y a deux façons dont une course de coursier
             | naît. L'ancienne rattache un enregistrement clando à la commande.
             | La nouvelle, celle de l'écran « commander une course », crée
             | directement une commande sans panier : c'est l'absence de panier
             | qui la trahit, une commande de produits en ayant toujours un.
             |
             | Sans ce second critère, toutes les demandes passées depuis
             | l'application arrivaient sur le mur étiquetées « commande », et le
             | comptoir cherchait un panier qui n'existait pas.
             */
            'type' => ($order->coursier || ($order->id_cart === null && $order->depart !== null))
                ? 'coursier'
                : 'commande',
            'coursier' => $order->coursier ? [
                'ref' => $order->coursier->ref,
                'status' => $order->coursier->status,
                'destination' => $order->coursier->destinationName,
            ] : null,
            'address' => $order->address,
            /*
             | Éléments propres à une course coursier : point de départ, photo du
             | colis et note du client. La photo n'était pas exposée, si bien que
             | le comptoir ne pouvait pas voir ce qu'il devait faire enlever.
             */
            'depart' => $order->depart,
            'note' => $order->note,
            /*
             | Appréciation laissée par le client, distincte de « note » qui est
             | le texte libre accompagnant une course. Deux choses différentes
             | sous un nom voisin : celle-ci porte l'émoticône et le commentaire.
             */
            'appreciation' => $this->appreciation($order->id),
            'image_url' => $order->image ? url('upload/' . $order->image) : null,
            'payment_method' => $order->payment_method,
            /*
             | Détails que le comptoir réclame sur une course de coursier : le
             | code que le destinataire devra donner, le numéro saisi au moment
             | de la demande — souvent celui du destinataire, pas du compte — et
             | la nature du colis. Sans eux, la fiche ne disait pas ce qu'il y
             | avait à enlever ni à qui le remettre.
             */
            'delivery_code' => $order->delivery_code,
            'phone_customer' => $order->phone_customer,
            'delivery_type' => $order->delivery_type,
            'reception_mode' => $order->reception_mode,
            'customer' => $order->user?->name,
            'phone' => $order->user?->phone,
            'whatsapp' => $order->user?->whatsapp,
            'email' => $order->user?->email,
            // Tous les horodatages sont convertis en heure du Cameroun : les dates
            // sont stockées en UTC, les afficher brutes décalerait tout d'une heure.
            'created_at' => $order->created_at?->toIso8601String(),
            'created_label' => $order->created_at?->setTimezone(self::FUSEAU)->format('H:i'),
            'created_day' => $this->libelleJour($order->created_at),
            'created_full' => $order->created_at?->setTimezone(self::FUSEAU)->locale('fr')->translatedFormat('l j F Y') . ' à '
                . $order->created_at?->setTimezone(self::FUSEAU)->format('H:i'),
            // Panier complet : le détail est consulté à la demande depuis le tableau,
            // et la liste est plafonnée à 12 commandes, donc le poids reste faible.
            // La boutique de chaque article est exposée : un même panier peut mêler
            // plusieurs boutiques, et le détail doit permettre de voir laquelle
            // fournit quoi.
            'items' => $items->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Article',
                'quantity' => (int) ($item->quantity ?? 1),
                'unit_price' => (int) ($item->price ?? $item->product?->price ?? 0),
                'amount' => (int) ($item->amount ?? 0),
                'shop' => $item->product?->shop?->shop_name,
            ])->values(),
            'shops' => $items->map(fn ($item) => $item->product?->shop?->shop_name)
                ->filter()
                ->unique()
                ->values(),
            'items_count' => $items->count(),
            'agent' => $order->agent?->user ? [
                'name' => $order->agent->user->name,
                'phone' => $order->agent->user->phone,
                'whatsapp' => $order->agent->user->whatsapp,
            ] : null,
        ];
    }
}
