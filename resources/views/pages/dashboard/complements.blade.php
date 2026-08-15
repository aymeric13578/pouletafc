<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;

name('dashboard.complements');

/*
| Compléments : ce qu'on propose en plus d'un produit.
|
| Un complément n'est pas une entité à part, c'est un produit — avec son prix,
| son stock et sa boutique. Une portion de frites se vend seule autant qu'elle
| accompagne un poulet. D'où un simple drapeau sur le produit, et un lien vers
| les plats qui le proposent.
|
| Écran distinct de la page Produits, qui gère le catalogue : ici on ne crée
| rien, on désigne et on rattache. Mélanger les deux aurait alourdi un
| formulaire déjà long pour un réglage qu'on ne touche qu'occasionnellement.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $vue = 'complements';

    /** Produit dont on modifie les compléments. */
    public $produitOuvert = null;

    public const VUES = [
        'complements' => 'Les compléments',
        'produits' => 'Rattacher à un produit',
    ];

    protected function catalogue()
    {
        return Product::query()
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where('name', 'like', $terme);
            });
    }

    public function getComplementsProperty()
    {
        return $this->catalogue()
            ->where('is_complement', true)
            ->withCount('proposePar')
            ->orderBy('name')
            ->paginate(15);
    }

    /** Produits ordinaires, auxquels on rattache des compléments. */
    public function getProduitsProperty()
    {
        return $this->catalogue()
            ->where(function ($q) {
                $q->where('is_complement', false)->orWhereNull('is_complement');
            })
            ->with('complements:id,name')
            ->orderBy('name')
            ->paginate(15);
    }

    /** Tous les compléments disponibles, pour les cases à cocher. */
    public function getChoixProperty()
    {
        return Product::where('is_complement', true)
            ->where('status', 'Success')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    public function getStatsProperty(): array
    {
        return [
            'complements' => Product::where('is_complement', true)->count(),
            'rattaches' => \Illuminate\Support\Facades\DB::table('product_complement')
                ->distinct('product_id')->count('product_id'),
            'liens' => \Illuminate\Support\Facades\DB::table('product_complement')->count(),
            'catalogue' => Product::count(),
        ];
    }

    /**
     * Désigne un produit comme complément, ou le retire.
     *
     * Le retirer ne le supprime pas du catalogue : il reste vendable seul. Mais
     * il cesse d'être proposé, et ses rattachements deviendraient trompeurs —
     * on les défait donc.
     */
    public function basculerComplement($id): void
    {
        $produit = Product::findOrFail($id);
        $devientComplement = ! $produit->is_complement;

        $produit->update(['is_complement' => $devientComplement]);

        if (! $devientComplement) {
            $produit->proposePar()->detach();

            $this->dispatch('notify', [
                'message' => $produit->name . " n'est plus un complément ; ses rattachements sont défaits.",
                'type' => 'success',
            ]);

            return;
        }

        $this->dispatch('notify', [
            'message' => $produit->name . ' peut désormais être proposé en complément.',
            'type' => 'success',
        ]);
    }

    public function ouvrirRattachement($id): void
    {
        $this->produitOuvert = $this->produitOuvert === $id ? null : $id;
    }

    /**
     * Rattache ou détache un complément d'un produit.
     */
    public function basculerLien($idProduit, $idComplement): void
    {
        $produit = Product::findOrFail($idProduit);

        if ($produit->complements()->where('complement_id', $idComplement)->exists()) {
            $produit->complements()->detach($idComplement);
            $message = 'Complément retiré de ' . $produit->name . '.';
        } else {
            // Un produit ne peut pas se proposer lui-même : la question n'aurait
            // pas de sens, et l'écran de vente afficherait le plat sous lui-même.
            if ((int) $idProduit === (int) $idComplement) {
                $this->dispatch('notify', [
                    'message' => 'Un produit ne peut pas être son propre complément.',
                    'type' => 'error',
                ]);

                return;
            }

            $produit->complements()->attach($idComplement);
            $message = 'Complément ajouté à ' . $produit->name . '.';
        }

        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedVue()
    {
        $this->resetPage();
        $this->produitOuvert = null;
    }
};
?>

<x-layouts.app title="Compléments">
    @volt
        <div>
            <div x-data x-on:notify.window="toastr[event.detail.type](event.detail.message)"></div>

            <x-ui.page-header title="Compléments"
                subtitle="Ce qu'on propose en plus d'un produit — frites, boisson, sauce" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Compléments" :value="$this->stats['complements']" tone="brand"
                    hint="produits proposables en accompagnement"
                    icon="M12 6v12m6-6H6" />

                <x-ui.stat label="Produits accompagnés" :value="$this->stats['rattaches']" tone="success"
                    :hint="'sur ' . $this->stats['catalogue'] . ' au catalogue'"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Rattachements" :value="$this->stats['liens']" tone="info"
                    icon="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />

                <x-ui.stat label="Jamais accompagnés"
                    :value="max(0, $this->stats['catalogue'] - $this->stats['complements'] - $this->stats['rattaches'])"
                    hint="aucun complément proposé"
                    tone="warning"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Nom du produit…" />

                <x-ui.select wire:model.live="vue" class="w-auto min-w-[14rem]">
                    @foreach (self::VUES as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            @if ($vue === 'complements')
                <div class="mt-4">
                    <p class="mb-3 text-xs text-gray-500">
                        Un complément reste vendable seul : le désigner ne le retire pas du catalogue,
                        il devient seulement proposable en accompagnement.
                    </p>

                    <x-ui.table target="search,vue,gotoPage,previousPage,nextPage"
                        :headers="['Produit', 'Prix', 'Proposé par', 'Statut', 'Action']">
                        @forelse ($this->complements as $complement)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $complement->name }}</p>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-700">
                                    {{ number_format((int) $complement->price, 0, ',', ' ') }} F
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ $complement->propose_par_count }} produit(s)
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ui.badge :tone="$complement->status === 'Success' ? 'success' : 'gray'">
                                        {{ $complement->status === 'Success' ? 'En vente' : 'Retiré' }}
                                    </x-ui.badge>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
                                    <button type="button" wire:click="basculerComplement({{ $complement->id }})"
                                            wire:confirm="Retirer ce complément ? Ses rattachements seront défaits."
                                            class="text-xs font-semibold text-red-600 hover:underline">
                                        Ne plus proposer
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty :colspan="5" title="Aucun complément"
                                message="Passez à « Rattacher à un produit » pour désigner un produit comme complément." />
                        @endforelse
                    </x-ui.table>

                    @if ($this->complements->hasPages())
                        <div class="mt-4">{{ $this->complements->links() }}</div>
                    @endif
                </div>
            @else
                <div class="mt-4">
                    <x-ui.table target="search,vue,gotoPage,previousPage,nextPage"
                        :headers="['Produit', 'Prix', 'Compléments rattachés', '']">
                        @forelse ($this->produits as $produit)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $produit->name }}</p>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-700">
                                    {{ number_format((int) $produit->price, 0, ',', ' ') }} F
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    @forelse ($produit->complements as $lie)
                                        <span class="mr-1 inline-block rounded bg-brand-50 px-2 py-0.5 text-xs text-brand-700">
                                            {{ $lie->name }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">Aucun</span>
                                    @endforelse
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button type="button" wire:click="ouvrirRattachement({{ $produit->id }})"
                                            class="mr-3 text-xs font-semibold text-brand-600 hover:underline">
                                        {{ $produitOuvert === $produit->id ? 'Fermer' : 'Rattacher' }}
                                    </button>

                                    <button type="button" wire:click="basculerComplement({{ $produit->id }})"
                                            class="text-xs font-semibold text-gray-500 hover:underline">
                                        En faire un complément
                                    </button>
                                </td>
                            </tr>

                            @if ($produitOuvert === $produit->id)
                                <tr>
                                    <td colspan="4" class="bg-gray-50 px-4 py-4">
                                        @if ($this->choix->isEmpty())
                                            <p class="text-xs text-gray-500">
                                                Aucun complément disponible. Désignez d'abord un produit
                                                avec « En faire un complément ».
                                            </p>
                                        @else
                                            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-600">
                                                Compléments proposés avec {{ $produit->name }}
                                            </p>

                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($this->choix as $complement)
                                                    @php $lie = $produit->complements->contains('id', $complement->id); @endphp
                                                    <button type="button"
                                                            wire:click="basculerLien({{ $produit->id }}, {{ $complement->id }})"
                                                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors
                                                                {{ $lie
                                                                    ? 'border-brand-500 bg-brand-600 text-white hover:bg-brand-700'
                                                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100' }}">
                                                        {{ $lie ? '✓ ' : '+ ' }}{{ $complement->name }}
                                                        <span class="opacity-70">
                                                            {{ number_format((int) $complement->price, 0, ',', ' ') }} F
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-ui.empty :colspan="4" title="Aucun produit"
                                message="Aucun produit ne correspond à cette recherche." />
                        @endforelse
                    </x-ui.table>

                    @if ($this->produits->hasPages())
                        <div class="mt-4">{{ $this->produits->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    @endvolt
</x-layouts.app>
