<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

name('dashboard.meilleurs-produits');

/*
| Ce qui se vend, et pour combien.
|
| La page Produits liste le catalogue : ce qui est en vente, à quel prix, en
| quel stock. Elle ne dit rien de ce qui part réellement. Un produit peut y
| trôner depuis six mois sans avoir jamais été commandé, et rien ne le
| distingue de celui qui part tous les jours.
|
| Les ventes ne se lisent pas dans la table des produits mais dans les paniers :
| order_details porte un panier, le panier porte ses lignes, chaque ligne un
| produit et une quantité. C'est cette chaîne qu'on remonte ici.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $periode = 'tout';

    public const PERIODES = [
        'tout' => 'Depuis toujours',
        'annee' => '12 derniers mois',
        'mois' => '30 derniers jours',
        'semaine' => '7 derniers jours',
    ];

    /**
     * Ce qui compte comme vendu.
     *
     * Une commande annulée n'a rien vendu ; une commande en cours non plus,
     * tant qu'elle n'est pas remise. Les compter gonflerait le classement de
     * produits qui n'ont peut-être jamais quitté l'étagère.
     */
    public const VENDUES = ['Success'];

    /*
     | Montant d'une ligne : quantité × cart_items.amount.
     |
     | Le nom de la colonne trompe : « amount » ne porte pas le montant de la
     | ligne mais le prix unitaire, figé au moment où le produit est mis au
     | panier (CartController y écrit $product->price). Le prendre pour un total
     | diviserait le chiffre d'affaires par la quantité commandée.
     |
     | On s'appuie sur lui plutôt que sur le prix courant du produit : celui-ci a
     | pu changer depuis, et le classement porterait alors sur des montants
     | jamais encaissés.
     */
    protected function depuis(): ?\Illuminate\Support\Carbon
    {
        return match ($this->periode) {
            'annee' => now()->subYear(),
            'mois' => now()->subDays(30),
            'semaine' => now()->subDays(7),
            default => null,
        };
    }

    /**
     * Ventes agrégées par produit.
     *
     * Une seule requête, jointures comprises : parcourir les commandes en PHP
     * pour additionner les lignes demanderait de charger tous les paniers.
     */
    protected function requeteDeBase()
    {
        $depuis = $this->depuis();

        return DB::table('cart_items')
            ->join('order_details', 'order_details.id_cart', '=', 'cart_items.cart_id')
            ->whereIn('order_details.status', self::VENDUES)
            ->when($depuis, fn ($q) => $q->where('order_details.created_at', '>=', $depuis))
            ->groupBy('cart_items.product_id')
            ->selectRaw('
                cart_items.product_id,
                SUM(cart_items.quantity) as quantite,
                COUNT(DISTINCT order_details.id) as commandes,
                SUM(cart_items.quantity * cart_items.amount) as montant
            ');
    }

    public function getProduitsProperty()
    {
        $lignes = $this->requeteDeBase()
            ->orderByDesc('quantite')
            ->paginate(20);

        // Les fiches produit en une requête : cart_items ne porte que des
        // identifiants.
        $fiches = Product::whereIn('id', collect($lignes->items())->pluck('product_id'))
            ->with('shop:id,shop_name')
            // Pas d'image : la colonne s'appelle « img » et l'écran ne s'en sert
            // pas. La demander sous un nom qui n'existe pas faisait échouer
            // toute la requête.
            ->get(['id', 'name', 'price', 'stock_init', 'id_shop'])
            ->keyBy('id');

        $collection = collect($lignes->items())->map(function ($ligne) use ($fiches) {
            $ligne->fiche = $fiches[$ligne->product_id] ?? null;

            return $ligne;
        });

        if ($this->search) {
            $terme = mb_strtolower($this->search);

            $collection = $collection->filter(function ($ligne) use ($terme) {
                return $ligne->fiche
                    && (str_contains(mb_strtolower((string) $ligne->fiche->name), $terme)
                        || str_contains(mb_strtolower((string) $ligne->fiche->shop?->shop_name), $terme));
            });
        }

        $lignes->setCollection($collection->values());

        return $lignes;
    }

    public function getStatsProperty(): array
    {
        /*
         | Totaux sur l'ensemble, sans le regroupement par produit.
         |
         | Réutiliser la requête de base telle quelle donnerait une ligne par
         | produit ; on la reconstruit donc à plat plutôt que d'essayer de lui
         | retirer son GROUP BY après coup.
         */
        $depuis = $this->depuis();

        $global = DB::table('cart_items')
            ->join('order_details', 'order_details.id_cart', '=', 'cart_items.cart_id')
            ->whereIn('order_details.status', self::VENDUES)
            ->when($depuis, fn ($q) => $q->where('order_details.created_at', '>=', $depuis))
            ->selectRaw('
                SUM(cart_items.quantity) as quantite,
                COUNT(DISTINCT cart_items.product_id) as produits,
                SUM(cart_items.quantity * cart_items.amount) as montant
            ')
            ->first();

        return [
            'quantite' => (int) ($global->quantite ?? 0),
            'produits' => (int) ($global->produits ?? 0),
            'montant' => (int) ($global->montant ?? 0),
            // Le catalogue complet, pour mesurer la part qui ne se vend jamais.
            'catalogue' => Product::count(),
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPeriode()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Meilleurs produits">
    @volt
        <div>
            <x-ui.page-header title="Meilleurs produits"
                subtitle="Ce qui a réellement été acheté, en quantité et en montant" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Articles vendus" :value="$this->stats['quantite']" tone="brand"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />

                <x-ui.stat label="Chiffre d'affaires"
                    :value="number_format($this->stats['montant'], 0, ',', ' ') . ' F'" tone="success"
                    hint="commandes livrées uniquement"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Références vendues" :value="$this->stats['produits']" tone="info"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Jamais vendues"
                    :value="max(0, $this->stats['catalogue'] - $this->stats['produits'])"
                    :hint="'sur ' . $this->stats['catalogue'] . ' au catalogue'"
                    :tone="($this->stats['catalogue'] - $this->stats['produits']) > 0 ? 'warning' : 'success'"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Produit ou boutique…" />

                <x-ui.select wire:model.live="periode" class="w-auto min-w-[12rem]">
                    @foreach (self::PERIODES as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,periode,gotoPage,previousPage,nextPage"
                    :headers="['Rang', 'Produit', 'Boutique', 'Quantité', 'Commandes', 'Montant', 'Stock']">
                    @forelse ($this->produits as $rang => $ligne)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-gray-500">
                                @if ($this->produits->currentPage() === 1 && $rang === 0)
                                    <span title="Le plus vendu">🏆</span>
                                @else
                                    {{ $this->produits->firstItem() + $rang }}
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">
                                    {{ $ligne->fiche?->name ?? 'Produit #' . $ligne->product_id }}
                                </p>
                                @if ($ligne->fiche?->price)
                                    <p class="text-xs text-gray-500">
                                        {{ number_format((int) $ligne->fiche->price, 0, ',', ' ') }} F l'unité
                                    </p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $ligne->fiche?->shop?->shop_name ?? '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ (int) $ligne->quantite }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-600">
                                {{ (int) $ligne->commandes }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ number_format((int) $ligne->montant, 0, ',', ' ') }} F
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @php $stock = (int) ($ligne->fiche?->stock_init ?? 0); @endphp
                                <x-ui.badge :tone="$stock <= 0 ? 'danger' : ($stock < 10 ? 'warning' : 'success')">
                                    {{ $stock }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="7" title="Aucune vente"
                            message="Aucun produit n'a été acheté sur cette période." />
                    @endforelse
                </x-ui.table>

                @if ($this->produits->hasPages())
                    <div class="mt-4">{{ $this->produits->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
