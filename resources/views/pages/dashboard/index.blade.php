<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\order_detail;

name('admin.index');

new class extends Component {
    /*
    | L'accueil était une grille de liens vers les modules. La navigation étant
    | désormais permanente dans la barre latérale, cette grille faisait doublon :
    | elle est remplacée par une vraie synthèse d'activité.
    |
    | Toutes les valeurs sont des propriétés calculées : rien n'est stocké dans
    | l'état public du composant, donc rien ne transite à chaque requête Livewire.
    */

    public function getStatsProperty(): array
    {
        $enCours = order_detail::whereIn('status', ['process', 'want', 'take', 'pending'])->count();

        return [
            'commandes' => order_detail::count(),
            'en_cours' => $enCours,
            'livrees' => order_detail::where('status', 'Success')->count(),
            'echecs' => order_detail::where('status', 'failed')->count(),
            'produits' => Product::count(),
            'produits_actifs' => Product::where('status', 'Success')->count(),
            'rupture' => Product::where('stock_init', '<', 10)->count(),
            'clients' => User::count(),
            'categories' => Category::count(),
        ];
    }

    public function getChiffreAffairesProperty(): int
    {
        return (int) order_detail::where('status', 'Success')->sum('price');
    }

    public function getRecentesProperty()
    {
        return order_detail::latest('id')->take(8)->get();
    }

    public function getStockFaibleProperty()
    {
        return Product::where('stock_init', '<', 10)
            ->orderBy('stock_init')
            ->take(5)
            ->get(['id', 'name', 'stock_init', 'status']);
    }
};
?>

<x-layouts.app title="Tableau de bord">
    @volt
        <div>
            <x-ui.page-header
                title="Vue d'ensemble"
                subtitle="Activité de la boutique en un coup d'œil" />

            {{-- Indicateurs principaux --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat
                    label="Commandes"
                    :value="number_format($this->stats['commandes'], 0, ',', ' ')"
                    :hint="$this->stats['en_cours'] . ' en cours'"
                    tone="brand"
                    icon="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />

                <x-ui.stat
                    label="Chiffre d'affaires"
                    :value="number_format($this->chiffreAffaires, 0, ',', ' ') . ' F'"
                    :hint="$this->stats['livrees'] . ' commandes livrées'"
                    tone="success"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat
                    label="Produits"
                    :value="$this->stats['produits']"
                    :hint="$this->stats['produits_actifs'] . ' en ligne'"
                    tone="accent"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />

                <x-ui.stat
                    label="Stock faible"
                    :value="$this->stats['rupture']"
                    hint="moins de 10 unités"
                    :tone="$this->stats['rupture'] > 0 ? 'warning' : 'success'"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3">

                {{-- Dernières commandes --}}
                <x-ui.card class="lg:col-span-2" :padded="false" title="Dernières commandes">
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" :href="route('dashboard.commands')">Tout voir</x-ui.button>
                    </x-slot:actions>

                    <x-ui.table :headers="['Référence', 'Montant', 'Statut', 'Date']">
                        @forelse ($this->recentes as $ligne)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">{{ $ligne->ref ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ number_format((int) $ligne->price, 0, ',', ' ') }} F</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @php
                                        $tone = match ($ligne->status) {
                                            'Success' => 'success',
                                            'failed' => 'danger',
                                            'pending' => 'warning',
                                            default => 'info',
                                        };
                                        $libelle = match ($ligne->status) {
                                            'Success' => 'Livrée',
                                            'failed' => 'Échec',
                                            'pending' => 'En attente',
                                            'process' => 'En cours',
                                            'take' => 'Prise en charge',
                                            default => $ligne->status,
                                        };
                                    @endphp
                                    <x-ui.badge :tone="$tone">{{ $libelle }}</x-ui.badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $ligne->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-ui.empty
                                :colspan="4"
                                title="Aucune commande"
                                message="Les commandes passées depuis la boutique ou l'application apparaîtront ici." />
                        @endforelse
                    </x-ui.table>
                </x-ui.card>

                {{-- Alertes stock --}}
                <x-ui.card :padded="false" title="Stock à surveiller" subtitle="Moins de 10 unités">
                    <x-slot:actions>
                        <x-ui.button size="sm" variant="secondary" :href="route('dashboard.products')">Produits</x-ui.button>
                    </x-slot:actions>

                    <ul class="divide-y divide-gray-100">
                        @forelse ($this->stockFaible as $produit)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $produit->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $produit->status === 'Success' ? 'En ligne' : 'Hors ligne' }}</p>
                                </div>
                                <x-ui.badge :tone="(int) $produit->stock_init === 0 ? 'danger' : 'warning'">
                                    {{ (int) $produit->stock_init }}
                                </x-ui.badge>
                            </li>
                        @empty
                            <li>
                                <x-ui.empty
                                    :in-row="false"
                                    title="Stocks au vert"
                                    message="Aucun produit sous le seuil de 10 unités." />
                            </li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>

            {{-- Raccourcis --}}
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['dashboard.products', 'Ajouter un produit', 'Créer une fiche et publier'],
                    ['dashboard.commands', 'Suivre les commandes', $this->stats['en_cours'] . ' en cours'],
                    ['dashboard.customers', 'Clients', $this->stats['clients'] . ' comptes'],
                    ['dashboard.statistiques', 'Statistiques', 'Analyse détaillée'],
                ] as [$route, $titre, $desc])
                    <a href="{{ route($route) }}"
                       class="group rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                        <p class="text-sm font-bold text-gray-900 group-hover:text-brand-700">{{ $titre }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $desc }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endvolt
</x-layouts.app>
