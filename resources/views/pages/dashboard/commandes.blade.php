<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\order_detail;

name('dashboard.commands');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $showDetailsModal = false;
    public $orderId = null;
    public $status = '';
    public $order_details = null;

    /*
    | Modification du panier d'une commande.
    |
    | L'écran ne permettait que de changer le statut : une quantité mal comprise
    | au téléphone ou un article en rupture obligeait à tout annuler et
    | ressaisir, en perdant l'historique et l'agent déjà attribué.
    |
    | La règle de calcul vit dans PanierDeCommande, partagée avec le mur des
    | commandes : recopiée, elle finirait par diverger et deux écrans donneraient
    | deux totaux pour la même commande.
    */
    public $panierOuvert = null;
    public $produitAAjouter = '';
    public $quantiteAAjouter = 1;

    private function panier(): \App\Support\PanierDeCommande
    {
        return app(\App\Support\PanierDeCommande::class);
    }

    /*
    | Cause d'un échec, affichée dans le panneau.
    |
    | Ouvrir un panier renvoyait une erreur 500 au corps vide : la page ne dit
    | rien, et les journaux du serveur ne sont pas consultables d'ici. Plutôt
    | que de continuer à deviner, l'écran affiche la cause — il est réservé à
    | l'équipe, et un message technique y vaut mieux qu'un écran mort.
    */
    public $erreurPanier = null;

    public function getCommandeOuverteProperty(): ?order_detail
    {
        return $this->panierOuvert ? order_detail::find($this->panierOuvert) : null;
    }

    public function getLignesProperty()
    {
        try {
            $commande = $this->commandeOuverte;

            return $commande ? $this->panier()->lignes($commande) : collect();
        } catch (\Throwable $e) {
            $this->signaler('lecture du panier', $e);

            return collect();
        }
    }

    /**
     * Catalogue proposé à l'ajout : seuls les produits en vente.
     *
     * Sans liste de colonnes restreinte : une seule colonne nommée qui
     * viendrait à manquer ferait échouer toute la requête, et donc l'écran.
     */
    public function getCatalogueProperty()
    {
        try {
            return \App\Models\Product::where('status', 'Success')
                ->orderBy('name')
                ->get(['id', 'name', 'price']);
        } catch (\Throwable $e) {
            $this->signaler('lecture du catalogue', $e);

            return collect();
        }
    }

    /**
     * Retient la cause d'un échec et la trace, sans casser l'écran.
     */
    private function signaler(string $quoi, \Throwable $e): void
    {
        $this->erreurPanier = $quoi . ' : ' . $e->getMessage();

        try {
            \Illuminate\Support\Facades\Log::error('Panier de commande — ' . $quoi, [
                'commande' => $this->panierOuvert,
                'message' => $e->getMessage(),
                'fichier' => $e->getFile() . ':' . $e->getLine(),
            ]);
        } catch (\Throwable) {
            // Journal indisponible : l'écran reste la seule voie, et il l'a déjà.
        }
    }

    public function ouvrirPanier($id): void
    {
        $this->panierOuvert = $this->panierOuvert === $id ? null : $id;
        $this->produitAAjouter = '';
        $this->quantiteAAjouter = 1;
        $this->erreurPanier = null;
    }

    /**
     * Refuse la correction et le dit, plutôt que de laisser croire à un effet.
     */
    private function refusSiFermee(?order_detail $commande): bool
    {
        if (! $commande) {
            return true;
        }

        if ($commande->id_cart === null) {
            $this->dispatch('notify', [
                'message' => "Cette commande n'a pas de panier : c'est une course.",
                'type' => 'error',
            ]);

            return true;
        }

        if (! $this->panier()->modifiable($commande)) {
            $this->dispatch('notify', [
                'message' => 'Cette commande est close, son panier ne peut plus changer.',
                'type' => 'error',
            ]);

            return true;
        }

        return false;
    }

    public function changerQuantite($idLigne, $delta): void
    {
        $commande = $this->commandeOuverte;

        if ($this->refusSiFermee($commande)) {
            return;
        }

        $ligne = \App\Models\CartItem::where('cart_id', $commande->id_cart)->find($idLigne);

        if (! $ligne) {
            $this->dispatch('notify', ['message' => 'Cet article ne fait pas partie de cette commande.', 'type' => 'error']);

            return;
        }

        $nom = $ligne->product?->name ?? 'Article';
        $nouvelle = (int) $ligne->quantity + (int) $delta;

        $this->panier()->definirQuantite($commande, $ligne, $nouvelle);

        $this->dispatch('notify', [
            'message' => $nouvelle < 1
                ? $nom . ' retiré de la commande.'
                : $nom . ' : ' . $nouvelle . ' unité' . ($nouvelle > 1 ? 's' : '') . '.',
            'type' => 'success',
        ]);
    }

    public function retirerLigne($idLigne): void
    {
        $commande = $this->commandeOuverte;

        if ($this->refusSiFermee($commande)) {
            return;
        }

        $ligne = \App\Models\CartItem::where('cart_id', $commande->id_cart)->find($idLigne);

        if (! $ligne) {
            $this->dispatch('notify', ['message' => 'Cet article ne fait pas partie de cette commande.', 'type' => 'error']);

            return;
        }

        $nom = $ligne->product?->name ?? 'Article';
        $this->panier()->retirer($commande, $ligne);

        $this->dispatch('notify', ['message' => $nom . ' retiré de la commande.', 'type' => 'success']);
    }

    public function ajouterProduit(): void
    {
        $commande = $this->commandeOuverte;

        if ($this->refusSiFermee($commande)) {
            return;
        }

        $produit = \App\Models\Product::find($this->produitAAjouter);

        if (! $produit) {
            $this->dispatch('notify', ['message' => 'Choisissez un produit à ajouter.', 'type' => 'error']);

            return;
        }

        $quantite = max(1, (int) $this->quantiteAAjouter);
        $this->panier()->ajouter($commande, $produit, $quantite);

        $this->produitAAjouter = '';
        $this->quantiteAAjouter = 1;

        $this->dispatch('notify', [
            'message' => $quantite . ' × ' . $produit->name . ' ajouté à la commande.',
            'type' => 'success',
        ]);
    }

    public function getOrdersProperty()
    {
        return order_detail::when($this->search, function ($q) {
                $q->where('ref', 'like', '%' . $this->search . '%')
                  ->orWhere('address', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->with('user')
            ->paginate(10);
    }

    public function getTotalOrdersProperty()
    {
        return order_detail::count();
    }

    public function getProcessingOrdersProperty()
    {
        return order_detail::whereIn('status', ['process', 'want', 'take'])->count();
    }

    public function getTotalAmountProperty()
    {
        return order_detail::sum('price');
    }

    /*
    | Changement du lieu de livraison.
    |
    | L'adresse était figée à la création de la commande : corriger un quartier
    | mal compris au téléphone obligeait à annuler et à ressaisir, en perdant
    | l'historique et l'agent déjà attribué. Le comptoir peut désormais choisir
    | un lieu enregistré, ou en créer un s'il n'existe pas encore — l'obliger à
    | passer par l'écran des lieux puis à revenir, c'est l'assurance qu'il
    | livrera au jugé.
    */
    public $lieuCommandeId = null;

    public $lieuChoisi = null;

    public $rechercheLieu = '';

    public $nouveauLieu = '';

    public $nouveauQuartier = '';

    public function getLieuxProperty()
    {
        $recherche = trim($this->rechercheLieu);

        return \App\Models\Location::with('quarter:id,name')
            ->when($recherche !== '', function ($requete) use ($recherche) {
                $requete->where('name', 'like', '%' . $recherche . '%')
                    ->orWhereHas('quarter', fn ($q) => $q->where('name', 'like', '%' . $recherche . '%'));
            })
            ->orderBy('name')
            ->limit(40)
            ->get();
    }

    public function ouvrirLeLieu($id): void
    {
        $this->lieuCommandeId = $id;
        $this->reset(['lieuChoisi', 'rechercheLieu', 'nouveauLieu', 'nouveauQuartier']);
    }

    public function fermerLeLieu(): void
    {
        $this->reset(['lieuCommandeId', 'lieuChoisi', 'rechercheLieu', 'nouveauLieu', 'nouveauQuartier']);
    }

    public function appliquerLeLieu($id = null): void
    {
        $commande = order_detail::find($this->lieuCommandeId);

        if (! $commande) {
            $this->fermerLeLieu();

            return;
        }

        $service = app(\App\Support\LieuDeLivraison::class);

        $lieu = $id ? \App\Models\Location::find($id) : null;

        if (! $lieu) {
            if (mb_strlen(trim($this->nouveauLieu)) < 2) {
                $this->dispatch('notify', [
                    'message' => 'Choisissez un lieu ou nommez-en un nouveau.',
                    'type' => 'error',
                ]);

                return;
            }

            $lieu = $service->creer([
                'name' => $this->nouveauLieu,
                'quarter_name' => $this->nouveauQuartier,
            ], auth()->id());
        }

        $lieu->loadMissing('quarter:id,name');
        $service->appliquer($commande, $lieu);

        $this->dispatch('notify', [
            'message' => 'Livraison déplacée vers ' . $service->libelle($lieu) . '.',
            'type' => 'success',
        ]);

        $this->fermerLeLieu();
    }

    public function openModal($id)
    {
        $this->orderId = $id;
        $order = order_detail::findOrFail($id);
        $this->status = $order->status;
        $this->showModal = true;
    }

    public function openDetailsModal($id)
    {
        $this->order_details = order_detail::with('user')->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['orderId', 'status']);
        $this->resetValidation();
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->order_details = null;
    }

    public function updateOrderStatus()
    {
        $this->validate([
            'status' => 'required|in:pending,Success,failed,waiting,process,want,take',
        ]);

        $order = order_detail::findOrFail($this->orderId);
        $order->status = $this->status;
        $order->save();

        $this->dispatch('notify', ['message' => 'Statut de la commande modifié avec succès !', 'type' => 'success']);
        $this->closeModal();
    }

    /**
     * Bascule le statut de paiement d'une commande.
     *
     * La colonne status_paiement est un enum('pending','Success','failed') : on
     * s'y tient. Le tunnel du site y écrivait 'paid'/'unpaid', valeurs que MySQL
     * refuse — aucune commande du web n'avait donc de paiement exploitable.
     */
    public function togglePaiement($id)
    {
        $order = order_detail::findOrFail($id);
        $order->status_paiement = $order->status_paiement === 'Success' ? 'pending' : 'Success';
        $order->save();

        $this->dispatch('notify', [
            'message' => $order->status_paiement === 'Success' ? 'Commande marquée payée !' : 'Paiement annulé.',
            'type' => 'success',
        ]);
    }
};
?>

<x-layouts.app>
    @volt
        <div class="container mx-auto px-2 mt-6">
            <!-- Barre de recherche -->
            <form class="flex items-center max-w-lg mx-auto mb-6">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher une commande (référence ou adresse)" />
                </div>
                <button type="submit" class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Rechercher
                </button>
            </form>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Commandes</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalOrders }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Commandes En Cours</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->processingOrders }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Montant Total</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ number_format($this->totalAmount, 2, ',', ' ') }} FCFA</p>
                </div>
            </div>

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Client</th>
                            <th class="py-3 px-4 text-gray-800">Montant</th>
                            <th class="py-3 px-4 text-gray-800">Adresse</th>
                            <th class="py-3 px-4 text-gray-800">Référence</th>
                            <th class="py-3 px-4 text-gray-800">Méthode de Paiement</th>
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Paiement</th>
                            <th class="py-3 px-4 text-gray-800">Date</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->orders as $order)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $order->id }}</td>
                                <td class="py-3 px-4">{{ $order->user ? $order->user->name : 'N/A' }}</td>
                                <td class="py-3 px-4">{{ number_format($order->price, 2, ',', ' ') }} FCFA</td>
                                <td class="py-3 px-4">
                                    {{ $order->address ?? 'N/A' }}
                                    <button type="button" wire:click="ouvrirLeLieu({{ $order->id }})"
                                        class="mt-1 block text-xs font-semibold text-blue-700 hover:underline">
                                        Changer le lieu
                                    </button>
                                </td>
                                <td class="py-3 px-4">{{ $order->ref }}</td>
                                <td class="py-3 px-4">
                                    @if ($order->payment_method === 'OM')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Orange Money</span>
                                    @elseif ($order->payment_method === 'MOMO')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">MTN Mobile Money</span>
                                    @elseif ($order->payment_method === 'LIVRAISON')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Cash à la livraison</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $order->status === 'Success' ? 'bg-green-100 text-green-800' :
                                           ($order->status === 'failed' ? 'bg-red-100 text-red-800' :
                                           ($order->status === 'waiting' ? 'bg-yellow-100 text-yellow-800' :
                                           ($order->status === 'process' ? 'bg-blue-100 text-blue-800' :
                                           ($order->status === 'want' ? 'bg-orange-100 text-orange-800' :
                                           ($order->status === 'take' ? 'bg-purple-100 text-purple-800' :
                                           'bg-gray-100 text-gray-800'))))) }}">
                                        {{ $order->status === 'pending' ? 'En attente' :
                                           ($order->status === 'Success' ? 'Terminé' :
                                           ($order->status === 'failed' ? 'Supprimé' :
                                           ($order->status === 'waiting' ? 'En attente d\'un agent' :
                                           ($order->status === 'process' ? 'En cours de livraison' :
                                           ($order->status === 'want' ? 'Colis souhaité' :
                                           'Colis pris'))))) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $order->status_paiement === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $order->status_paiement === 'Success' ? 'Payée' : 'Non payée' }}
                                    </span>
                                    <button wire:click="togglePaiement({{ $order->id }})"
                                            class="mt-1.5 block rounded-lg px-2.5 py-1 text-xs font-bold text-white transition-colors
                                                {{ $order->status_paiement === 'Success' ? 'bg-gray-400 hover:bg-gray-500' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                                        {{ $order->status_paiement === 'Success' ? 'Annuler' : 'Marquer payée' }}
                                    </button>
                                </td>
                                <td class="py-3 px-4">{{ optional($order->created_at)->format('Y-m-d') }}</td>
                                <td class="py-3 px-4">
                                    <button wire:click="openDetailsModal({{ $order->id }})" class="text-blue-600 hover:text-blue-800 mr-2" title="Voir les détails">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button wire:click="openModal({{ $order->id }})" class="text-gray-600 hover:text-gray-800 mr-2" title="Modifier le statut">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>

                                    {{-- Modifier le panier : produits et quantités. --}}
                                    @if ($order->id_cart)
                                        <button wire:click="ouvrirPanier({{ $order->id }})"
                                                class="text-xs font-semibold text-indigo-600 hover:underline">
                                            {{ $panierOuvert === $order->id ? 'Fermer' : 'Modifier le panier' }}
                                        </button>
                                    @else
                                        {{-- Une course n'a pas de panier : elle transporte un colis. --}}
                                        <span class="text-xs text-gray-400" title="Course de coursier">—</span>
                                    @endif
                                </td>
                            </tr>

                            @if ($panierOuvert === $order->id)
                                <tr wire:key="panier-{{ $order->id }}">
                                    <td colspan="9" class="bg-gray-50 px-4 py-4">
                                        @php
                                            $modifiable = app(\App\Support\PanierDeCommande::class)->modifiable($order);
                                        @endphp

                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-bold uppercase tracking-wider text-gray-600">
                                                Panier de {{ $order->ref }}
                                            </p>

                                            @unless ($modifiable)
                                                <span class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                                    Commande close : le panier ne peut plus changer.
                                                </span>
                                            @endunless
                                        </div>

                                        @if ($erreurPanier)
                                            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                                                <p class="text-xs font-bold text-red-800">Le panier n'a pas pu être lu</p>
                                                <p class="mt-1 break-words font-mono text-[11px] text-red-700">{{ $erreurPanier }}</p>
                                            </div>
                                        @endif

                                        <div class="mt-3 space-y-2">
                                            @forelse ($this->lignes as $ligne)
                                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            {{ $ligne->product?->name ?? 'Produit supprimé' }}
                                                            @if ($ligne->product?->is_complement)
                                                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">complément</span>
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500">
                                                            {{ number_format((int) $ligne->amount, 0, ',', ' ') }} F l'unité
                                                        </p>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <button type="button" wire:click="changerQuantite({{ $ligne->id }}, -1)"
                                                                @disabled(! $modifiable)
                                                                class="h-7 w-7 rounded-full bg-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                                            −
                                                        </button>

                                                        <span class="w-8 text-center text-sm font-bold tabular-nums">
                                                            {{ (int) $ligne->quantity }}
                                                        </span>

                                                        <button type="button" wire:click="changerQuantite({{ $ligne->id }}, 1)"
                                                                @disabled(! $modifiable)
                                                                class="h-7 w-7 rounded-full bg-gray-200 text-sm font-bold text-gray-700 hover:bg-gray-300 disabled:opacity-40">
                                                            +
                                                        </button>
                                                    </div>

                                                    <span class="w-24 text-right text-sm font-bold tabular-nums text-gray-900">
                                                        {{ number_format((int) $ligne->quantity * (int) $ligne->amount, 0, ',', ' ') }} F
                                                    </span>

                                                    <button type="button" wire:click="retirerLigne({{ $ligne->id }})"
                                                            @disabled(! $modifiable)
                                                            wire:confirm="Retirer cet article de la commande ?"
                                                            class="text-xs font-semibold text-red-600 hover:underline disabled:opacity-40">
                                                        Retirer
                                                    </button>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-500">Ce panier est vide.</p>
                                            @endforelse
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3">
                                            <div class="text-sm">
                                                <span class="text-gray-500">Panier</span>
                                                <span class="ml-1 font-bold tabular-nums text-gray-900">
                                                    {{ number_format((int) $order->panier_price, 0, ',', ' ') }} F
                                                </span>
                                                <span class="ml-3 text-gray-500">Livraison</span>
                                                <span class="ml-1 tabular-nums text-gray-700">
                                                    {{ number_format((int) $order->delivery_fees, 0, ',', ' ') }} F
                                                </span>
                                                <span class="ml-3 text-gray-500">Total</span>
                                                <span class="ml-1 font-bold tabular-nums text-indigo-700">
                                                    {{ number_format((int) $order->price, 0, ',', ' ') }} F
                                                </span>
                                            </div>
                                        </div>

                                        @if ($modifiable)
                                            <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-200 pt-3">
                                                <div class="min-w-[16rem] flex-1">
                                                    <label class="block text-xs font-semibold text-gray-600">Ajouter un produit</label>
                                                    <select wire:model="produitAAjouter"
                                                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                        <option value="">Choisir…</option>
                                                        @foreach ($this->catalogue as $produit)
                                                            <option value="{{ $produit->id }}">
                                                                {{ $produit->name }} — {{ number_format((int) $produit->price, 0, ',', ' ') }} F
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="w-24">
                                                    <label class="block text-xs font-semibold text-gray-600">Quantité</label>
                                                    <input type="number" min="1" wire:model="quantiteAAjouter"
                                                           class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                </div>

                                                <button type="button" wire:click="ajouterProduit"
                                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">
                                                    Ajouter
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <!-- Pagination -->
                <div class="mt-4">
                    {{ $this->orders->links() }}
                </div>
            </div>

            <!-- Modal pour modifier le statut -->
            <div wire:model="showModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Modifier le Statut de la Commande</h2>
                    <form wire:submit.prevent="updateOrderStatus" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="status">Statut <span class="text-red-500">*</span></label>
                            <select id="status" wire:model="status" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                <option value="">Sélectionner</option>
                                <option value="pending">En attente</option>
                                <option value="Success">Terminé</option>
                                <option value="failed">Supprimé</option>
                                <option value="waiting">En attente d'un agent</option>
                                <option value="process">En cours de livraison</option>
                                <option value="want">Colis souhaité</option>
                                <option value="take">Colis pris</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">Modifier</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal pour visualiser les détails -->
            <div wire:model="showDetailsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showDetailsModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails de la Commande</h2>
                    @if ($order_details)
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID</label>
                                    <p class="text-sm">{{ $order_details->id }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Client</label>
                                    <p class="text-sm">{{ $order_details->user ? $order_details->user->name : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Montant</label>
                                    <p class="text-sm">{{ number_format($order_details->price, 2, ',', ' ') }} FCFA</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Quantité</label>
                                    <p class="text-sm">{{ $order_details->qty ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Statut</label>
                                    <p class="text-sm">
                                        {{ $order_details->status === 'pending' ? 'En attente' :
                                           ($order_details->status === 'Success' ? 'Terminé' :
                                           ($order_details->status === 'failed' ? 'Supprimé' :
                                           ($order_details->status === 'waiting' ? 'En attente d\'un agent' :
                                           ($order_details->status === 'process' ? 'En cours de livraison' :
                                           ($order_details->status === 'want' ? 'Colis souhaité' :
                                           'Colis pris'))))) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de création</label>
                                    <p class="text-sm">{{ optional($order_details->created_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de mise à jour</label>
                                    <p class="text-sm">{{ optional($order_details->updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Panier</label>
                                    <p class="text-sm">{{ $order_details->id_cart ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Adresse</label>
                                    <p class="text-sm">{{ $order_details->address ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Référence</label>
                                    <p class="text-sm">{{ $order_details->ref ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Agent</label>
                                    <p class="text-sm">{{ $order_details->id_agent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Utilisateur</label>
                                    <p class="text-sm">{{ $order_details->id_user ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Méthode de Paiement</label>
                                    <p class="text-sm">
                                        @if ($order_details->payment_method === 'OM') Orange Money
                                        @elseif ($order_details->payment_method === 'MOMO') MTN Mobile Money
                                        @elseif ($order_details->payment_method === 'LIVRAISON') Cash à la livraison
                                        @else N/A
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Commission Vendeur</label>
                                    <p class="text-sm">{{ $order_details->commission_seller ? number_format($order_details->commission_seller, 2, ',', ' ') . ' FCFA' : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Client</label>
                                    <p class="text-sm">{{ $order_details->longitude ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Client</label>
                                    <p class="text-sm">{{ $order_details->latitude ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Agent</label>
                                    <p class="text-sm">{{ $order_details->lonAgent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Agent</label>
                                    <p class="text-sm">{{ $order_details->latAgent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Matricule Véhicule</label>
                                    <p class="text-sm">{{ $order_details->matricule_vehicule ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Boutique</label>
                                    <p class="text-sm">{{ $order_details->latShop ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Boutique</label>
                                    <p class="text-sm">{{ $order_details->lonShop ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Nom Boutique</label>
                                    <p class="text-sm">{{ $order_details->shop_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Code de Livraison</label>
                                    <p class="text-sm">{{ $order_details->delivery_code ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Commission Agent</label>
                                    <p class="text-sm">{{ $order_details->commission_agent ? number_format($order_details->commission_agent, 2, ',', ' ') . ' FCFA' : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeDetailsModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Toastr -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
            <script>
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            </script>
        </div>
            {{-- Choix du lieu de livraison : la liste enregistrée, ou un lieu neuf. --}}
            @if ($lieuCommandeId)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                     wire:key="lieu-{{ $lieuCommandeId }}">
                    <div class="flex max-h-[85vh] w-full max-w-xl flex-col rounded-2xl bg-white p-5 shadow-2xl">
                        <h2 class="text-lg font-bold text-gray-900">Lieu de livraison</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Choisissez un lieu enregistré, ou créez-en un : il rejoindra la liste commune
                            et servira aux prochaines commandes comme aux agents.
                        </p>

                        <input type="search" wire:model.live.debounce.250ms="rechercheLieu"
                               placeholder="Chercher un lieu ou un quartier…"
                               class="mt-4 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0" />

                        <div class="mt-3 min-h-0 flex-1 overflow-y-auto rounded-xl border border-gray-100">
                            @forelse ($this->lieux as $lieu)
                                <button type="button" wire:click="appliquerLeLieu({{ $lieu->id }})"
                                        class="flex w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-2.5 text-left transition-colors hover:bg-gray-50">
                                    <span class="text-sm font-semibold text-gray-800">
                                        {{ $lieu->name }}@if ($lieu->quarter) — {{ $lieu->quarter->name }}@endif
                                    </span>
                                    @if (! $lieu->latitude || ! $lieu->longitude)
                                        {{-- Un lieu sans coordonnées nomme la livraison mais ne se place
                                             pas sur la carte : le comptoir doit le savoir. --}}
                                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                                            sans repère
                                        </span>
                                    @endif
                                </button>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-gray-500">
                                    Aucun lieu ne correspond. Créez-le juste en dessous.
                                </p>
                            @endforelse
                        </div>

                        <div class="mt-4 grid gap-2 border-t border-gray-100 pt-4 sm:grid-cols-2">
                            <input type="text" wire:model="nouveauLieu" placeholder="Nouveau lieu"
                                   class="rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0" />
                            <input type="text" wire:model="nouveauQuartier" placeholder="Quartier"
                                   class="rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0" />
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-2">
                            <button type="button" wire:click="fermerLeLieu"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">
                                Fermer
                            </button>
                            <button type="button" wire:click="appliquerLeLieu"
                                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                                Enregistrer et livrer ici
                            </button>
                        </div>
                    </div>
                </div>
            @endif

    @endvolt
</x-layouts.app>
