<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Shop;
use App\Models\order_detail;

name('merchand.commandes');

/*
| Commandes contenant au moins un produit de la boutique.
|
| Il n'existe pas de lien direct entre une commande et une boutique : le
| rattachement passe par le panier, ses lignes, puis le produit. D'où les
| whereHas imbriqués ci-dessous plutôt qu'un simple where('id_shop').
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $statutFiltre = '';

    public function getBoutiqueProperty(): Shop
    {
        return Shop::where('id_user', auth()->id())->firstOrFail();
    }

    protected function requeteDeBase()
    {
        $boutiqueId = $this->boutique->id;

        return order_detail::with(['user:id,name,phone', 'carts.cart_items.product:id,name,price,id_shop'])
            ->whereHas('carts.cart_items.product', fn ($q) => $q->where('id_shop', $boutiqueId));
    }

    public function getCommandesProperty()
    {
        return $this->requeteDeBase()
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(fn ($sub) => $sub->where('ref', 'like', $terme)->orWhere('address', 'like', $terme));
            })
            ->when($this->statutFiltre, fn ($q) => $q->where('status', $this->statutFiltre))
            ->latest('id')
            ->paginate(10);
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => $this->requeteDeBase()->count(),
            'en_cours' => $this->requeteDeBase()->whereIn('status', ['pending', 'want', 'take', 'process'])->count(),
            'livrees' => $this->requeteDeBase()->where('status', 'Success')->count(),
            'ca' => (int) $this->requeteDeBase()->where('status', 'Success')->sum('price'),
        ];
    }

    /** Lignes du panier appartenant à cette boutique uniquement. */
    public function articlesDeLaBoutique(order_detail $commande)
    {
        return ($commande->carts?->cart_items ?? collect())
            ->filter(fn ($item) => (int) ($item->product?->id_shop ?? 0) === (int) $this->boutique->id);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.merchand title="Mes commandes">
    @volt
        <div>
            <x-ui.page-header title="Mes commandes"
                subtitle="Commandes contenant au moins un produit de votre boutique" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Commandes" :value="$this->stats['total']" tone="brand"
                    icon="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />

                <x-ui.stat label="En cours" :value="$this->stats['en_cours']" tone="warning"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Livrées" :value="$this->stats['livrees']" tone="success"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Chiffre d'affaires"
                    :value="number_format($this->stats['ca'], 0, ',', ' ') . ' F'" tone="accent"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Référence ou adresse…" />

                <x-ui.select wire:model.live="statutFiltre" class="w-auto min-w-[12rem]">
                    <option value="">Tous les statuts</option>
                    <option value="pending">En attente</option>
                    <option value="want">À prendre</option>
                    <option value="process">En cours</option>
                    <option value="take">Prise en charge</option>
                    <option value="Success">Livrée</option>
                    <option value="failed">Échec</option>
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,statutFiltre,gotoPage,previousPage,nextPage"
                    :headers="['Référence', 'Client', 'Vos articles', 'Montant total', 'Statut', 'Date']">
                    @forelse ($this->commandes as $commande)
                        @php $mesArticles = $this->articlesDeLaBoutique($commande); @endphp
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-gray-900">
                                {{ $commande->ref ?: '#' . $commande->id }}
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $commande->user?->name ?? '—' }}</p>
                                @if ($commande->user?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $commande->user->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700">
                                @forelse ($mesArticles as $article)
                                    <p class="truncate">
                                        {{ $article->product?->name }}
                                        <span class="font-bold text-gray-900">×{{ (int) $article->quantity }}</span>
                                    </p>
                                @empty
                                    <span class="text-gray-300">—</span>
                                @endforelse
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-gray-900">
                                {{ number_format((int) $commande->price, 0, ',', ' ') }} F
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @php
                                    $tone = match ($commande->status) {
                                        'Success' => 'success',
                                        'failed' => 'danger',
                                        'pending', 'want' => 'warning',
                                        default => 'info',
                                    };
                                    $libelle = match ($commande->status) {
                                        'Success' => 'Livrée',
                                        'failed' => 'Échec',
                                        'pending' => 'En attente',
                                        'want' => 'À prendre',
                                        'process' => 'En cours',
                                        'take' => 'Prise en charge',
                                        default => $commande->status,
                                    };
                                @endphp
                                <x-ui.badge :tone="$tone">{{ $libelle }}</x-ui.badge>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ $commande->created_at?->setTimezone('Africa/Douala')->format('d/m/Y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="6" title="Aucune commande"
                            message="Les commandes contenant vos produits apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->commandes->hasPages())
                    <div class="mt-4">{{ $this->commandes->links() }}</div>
                @endif
            </div>

            <p class="mt-4 text-xs text-gray-500">
                Le montant affiché est celui de la commande entière, qui peut contenir des produits d'autres boutiques.
                La colonne « Vos articles » ne liste que les vôtres.
            </p>
        </div>
    @endvolt
</x-layouts.merchand>
