<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Promotion;

name('dashboard.promotions');

/**
 * Validation des promotions marchand.
 *
 * Une promotion créée depuis « Ma boutique » (MaBoutiqueController::
 * saveMyShopPromotion) naît en statut 'pending' et n'apparaît jamais au
 * client tant que ce statut n'est pas passé à 'Success' — exactement comme
 * un produit. Cet écran manquait : la fonctionnalité existait de bout en
 * bout côté mobile et API, mais aucune page ne permettait de faire franchir
 * cette étape, laissant chaque promotion bloquée indéfiniment en attente.
 */
new class extends Component {
    public $search = '';

    public function toggleStatus($promotionId)
    {
        $promotion = Promotion::findOrFail($promotionId);
        $newStatus = $promotion->status === 'Success' ? 'pending' : 'Success';
        $promotion->update(['status' => $newStatus]);
        $message = $newStatus === 'Success' ? 'Promotion activée avec succès !' : 'Promotion désactivée.';
        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
    }

    public function getPromotionsProperty()
    {
        return Promotion::with(['shop', 'product'])
            ->when($this->search, function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'));
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getTotalCountProperty()
    {
        return Promotion::count();
    }

    public function getPendingCountProperty()
    {
        return Promotion::where('status', '!=', 'Success')->count();
    }

    public function getActiveCountProperty()
    {
        return Promotion::where('status', 'Success')->count();
    }
};
?>

<x-layouts.app>
    @volt
        <div>
            <div class="container mx-auto px-2 mt-6">

                <!-- Barre de recherche -->
                <form class="flex items-center max-w-lg mx-auto mb-6">
                    <label for="search" class="sr-only">Rechercher</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                            </svg>
                        </div>
                        <input type="text" id="search" wire:model.live="search"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5"
                            placeholder="Rechercher une promotion ou un produit" />
                    </div>
                </form>

                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Total promotions</h3>
                        <p class="text-3xl font-bold text-indigo-600">{{ $this->totalCount }}</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">En attente de validation</h3>
                        <p class="text-3xl font-bold text-amber-500">{{ $this->pendingCount }}</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Actives</h3>
                        <p class="text-3xl font-bold text-emerald-600">{{ $this->activeCount }}</p>
                    </div>
                </div>

                <!-- Promotions en cartes -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse ($this->promotions as $promotion)
                        @php
                            $produit = $promotion->product;
                            $image = product_image_url($produit?->img ?: $produit?->product_image1);
                            $prixAvant = (float) ($produit->price ?? 0);
                            $prixApres = $promotion->prixApres($prixAvant);
                            $remise = $promotion->discount_type === 'percentage'
                                ? '-' . rtrim(rtrim(number_format((float) $promotion->discount_value, 1), '0'), '.') . ' %'
                                : '-' . number_format((float) $promotion->discount_value, 0, ',', ' ') . ' F';
                        @endphp

                        <div wire:key="promo-{{ $promotion->id }}"
                             class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:shadow-md">

                            <div class="relative h-36 bg-gray-100">
                                @if ($produit)
                                    <img src="{{ $image }}" alt="{{ $produit->name }}"
                                         class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full items-center justify-center text-xs text-gray-400">
                                        Produit supprimé
                                    </div>
                                @endif

                                <span class="absolute right-2 top-2 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                    {{ $remise }}
                                </span>
                                <span class="absolute left-2 top-2 rounded-full px-2 py-0.5 text-[10px] font-bold text-white
                                    {{ $promotion->status === 'Success' ? 'bg-emerald-600' : 'bg-amber-500' }}">
                                    {{ $promotion->status === 'Success' ? 'Active' : 'En attente' }}
                                </span>
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <p class="font-semibold text-gray-900">{{ $promotion->title }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $produit->name ?? '—' }}
                                    @if ($promotion->shop) · {{ $promotion->shop->shop_name }} @endif
                                </p>

                                <div class="mt-2 flex items-baseline gap-2">
                                    @if ($produit)
                                        <span class="text-xs text-gray-400 line-through">
                                            {{ number_format($prixAvant, 0, ',', ' ') }} F
                                        </span>
                                        <span class="text-lg font-bold text-indigo-700">
                                            {{ number_format($prixApres, 0, ',', ' ') }} F
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ optional($promotion->starts_at)->format('d/m/Y') }}
                                    →
                                    {{ optional($promotion->ends_at)->format('d/m/Y') }}
                                </p>

                                <div class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                                    <button wire:click="toggleStatus({{ $promotion->id }})"
                                            class="rounded-lg px-2.5 py-1 text-xs font-semibold
                                                {{ $promotion->status === 'Success'
                                                    ? 'border border-gray-400 text-gray-700 hover:bg-gray-100'
                                                    : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                        {{ $promotion->status === 'Success' ? 'Désactiver' : 'Activer' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-16 text-center text-sm text-gray-500">
                            Aucune promotion pour le moment.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>
