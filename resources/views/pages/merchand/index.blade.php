<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Shop;

name('merchand.index');

/*
| Synthèse de la boutique du marchand connecté.
|
| Toutes les requêtes partent de la boutique résolue par EnsureUserHasShop :
| aucune ne doit pouvoir remonter à une autre boutique.
*/
new class extends Component {
    public function getBoutiqueProperty(): Shop
    {
        return Shop::where('id_user', auth()->id())->firstOrFail();
    }

    public function getStatsProperty(): array
    {
        $produits = Product::where('id_shop', $this->boutique->id);

        return [
            'produits' => (clone $produits)->count(),
            'en_ligne' => (clone $produits)->where('status', 'Success')->count(),
            'en_attente' => (clone $produits)->where('status', 'pending')->count(),
            'rupture' => (clone $produits)->where('stock_init', '<', 10)->count(),
            'valeur_stock' => (int) (clone $produits)->sum(\DB::raw('price * COALESCE(stock_init, 0)')),
        ];
    }

    public function getDerniersProduitsProperty()
    {
        return Product::where('id_shop', $this->boutique->id)
            ->latest('id')
            ->take(6)
            ->get(['id', 'name', 'price', 'stock_init', 'status', 'img']);
    }

    public function getStockFaibleProperty()
    {
        return Product::where('id_shop', $this->boutique->id)
            ->where('stock_init', '<', 10)
            ->orderBy('stock_init')
            ->take(5)
            ->get(['id', 'name', 'stock_init']);
    }
};
?>

<x-layouts.merchand title="Tableau de bord">
    @volt
        <div>
            <x-ui.page-header
                :title="$this->boutique->shop_name"
                :subtitle="'Votre boutique · ' . ($this->boutique->type ?: 'Indépendant') . ($this->boutique->city ? ' · ' . $this->boutique->city : '')">
                <x-slot:actions>
                    <x-ui.button :href="route('merchand.produits')">Gérer mes produits</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            @if ($this->boutique->status !== 'Success')
                <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4">
                    <p class="font-bold text-amber-900">Votre boutique est désactivée</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Vos produits ne sont pas visibles par les clients. Contactez l'administrateur de Poulet AFC pour la réactiver.
                    </p>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Mes produits" :value="$this->stats['produits']"
                    :hint="$this->stats['en_ligne'] . ' en ligne'" tone="brand"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />

                <x-ui.stat label="En attente de validation" :value="$this->stats['en_attente']"
                    hint="pas encore visibles"
                    :tone="$this->stats['en_attente'] > 0 ? 'warning' : 'success'"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Stock faible" :value="$this->stats['rupture']" hint="moins de 10 unités"
                    :tone="$this->stats['rupture'] > 0 ? 'danger' : 'success'"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />

                <x-ui.stat label="Valeur du stock"
                    :value="number_format($this->stats['valeur_stock'], 0, ',', ' ') . ' F'"
                    tone="success"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">
                <x-ui.card class="lg:col-span-2" :padded="false" title="Derniers produits ajoutés">
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" :href="route('merchand.produits')">Tout voir</x-ui.button>
                    </x-slot:actions>

                    <x-ui.table :headers="['Produit', 'Prix', 'Stock', 'Statut']">
                        @forelse ($this->derniersProduits as $produit)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ product_image_url($produit->img) }}" alt=""
                                             class="h-10 w-10 rounded-lg border border-gray-200 object-cover">
                                        <span class="font-semibold text-gray-900">{{ $produit->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700">
                                    {{ number_format((int) $produit->price, 0, ',', ' ') }} F
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-700">{{ (int) $produit->stock_init }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ui.badge :tone="$produit->status === 'Success' ? 'success' : 'warning'">
                                        {{ $produit->status === 'Success' ? 'En ligne' : 'En attente' }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty :colspan="4" title="Aucun produit"
                                message="Ajoutez votre premier produit pour qu'il apparaisse au catalogue." />
                        @endforelse
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card :padded="false" title="Stock à réapprovisionner" subtitle="Moins de 10 unités">
                    <ul class="divide-y divide-gray-100">
                        @forelse ($this->stockFaible as $produit)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $produit->name }}</p>
                                <x-ui.badge :tone="(int) $produit->stock_init === 0 ? 'danger' : 'warning'">
                                    {{ (int) $produit->stock_init }}
                                </x-ui.badge>
                            </li>
                        @empty
                            <li>
                                <x-ui.empty :in-row="false" title="Stocks au vert"
                                    message="Aucun produit sous le seuil de 10 unités." />
                            </li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>
        </div>
    @endvolt
</x-layouts.merchand>
