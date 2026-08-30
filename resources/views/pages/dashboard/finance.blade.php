<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\BoutiqueFacturation;
use App\Models\Product;
use App\Models\Shop;

name('dashboard.finance');

/*
| Ce que l'entreprise perçoit sur les boutiques hébergées.
|
| Deux modèles, exclusifs pour une même boutique :
|
|  - commission : les prix affichés au client sont majorés d'un pourcentage,
|    et l'écart revient à l'entreprise. Le marchand ne voit jamais que son
|    prix de base — la majoration lui est invisible, y compris dans « Ma
|    boutique ». La portée est la boutique entière ou une sélection de
|    produits, chacun pouvant porter son propre taux.
|  - abonnement : rien n'est majoré, la boutique doit un montant qui lui est
|    propre. Son espace marchand l'avertit à trois jours de l'échéance.
|
| Le calcul lui-même vit dans App\Support\MajorationBoutique, pas ici : il
| doit valoir partout où un prix part vers l'application cliente, et cet
| écran n'en est que le réglage.
*/
new class extends Component {
    public $showModal = false;
    public $facturationId = null;

    public $shop_id = '';
    public $mode = BoutiqueFacturation::MODE_COMMISSION;
    public $portee = BoutiqueFacturation::PORTEE_BOUTIQUE;
    public $taux = '';
    public $abonnement_montant = '';
    public $abonnement_periodicite = 'mensuel';
    public $abonnement_echeance = '';
    public $actif = true;
    /** @var array<int, int> Produits majorés, en portée « produits ». */
    public $produitsSelectionnes = [];

    public function getFacturationsProperty()
    {
        return BoutiqueFacturation::with(['shop', 'produits'])
            ->orderByDesc('actif')
            ->orderBy('shop_id')
            ->get();
    }

    public function getBoutiquesProperty()
    {
        return Shop::orderBy('shop_name')->get(['id', 'shop_name']);
    }

    /** Produits de la boutique en cours d'édition, pour la portée « produits ». */
    public function getProduitsBoutiqueProperty()
    {
        if (! $this->shop_id || $this->portee !== BoutiqueFacturation::PORTEE_PRODUITS) {
            return collect();
        }

        return Product::where('id_shop', $this->shop_id)
            ->where('status', 'Success')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    /*
    | Repères de tête : ce que rapportent les deux modèles.
    |
    | La commission n'est pas sommable à l'avance — elle dépend de ce qui sera
    | vendu — donc on compte les boutiques concernées et le taux moyen plutôt
    | que d'afficher un montant inventé. L'abonnement, lui, est un montant dû,
    | ramené au mois pour être comparable d'une périodicité à l'autre.
    */
    public function getResumeProperty()
    {
        $facturations = $this->facturations->where('actif', true);

        $commissions = $facturations->where('mode', BoutiqueFacturation::MODE_COMMISSION);
        $abonnements = $facturations->where('mode', BoutiqueFacturation::MODE_ABONNEMENT);

        $mensuel = $abonnements->sum(function (BoutiqueFacturation $f) {
            $mois = BoutiqueFacturation::PERIODICITES[$f->abonnement_periodicite] ?? 1;

            return $mois > 0 ? ($f->abonnement_montant ?? 0) / $mois : 0;
        });

        return [
            'commissions' => $commissions->count(),
            'taux_moyen' => $commissions->count() ? round($commissions->avg('taux'), 2) : 0,
            'abonnements' => $abonnements->count(),
            'mensuel' => (int) round($mensuel),
            'echeances' => $abonnements->filter(fn (BoutiqueFacturation $f) => $f->doitAvertir())->count(),
        ];
    }

    public function openModal($id = null)
    {
        $this->resetValidation();

        if ($id === null) {
            $this->facturationId = null;
            $this->shop_id = '';
            $this->mode = BoutiqueFacturation::MODE_COMMISSION;
            $this->portee = BoutiqueFacturation::PORTEE_BOUTIQUE;
            $this->taux = '';
            $this->abonnement_montant = '';
            $this->abonnement_periodicite = 'mensuel';
            $this->abonnement_echeance = '';
            $this->actif = true;
            $this->produitsSelectionnes = [];
            $this->showModal = true;

            return;
        }

        $facturation = BoutiqueFacturation::with('produits')->findOrFail($id);

        $this->facturationId = $facturation->id;
        $this->shop_id = $facturation->shop_id;
        $this->mode = $facturation->mode;
        $this->portee = $facturation->portee;
        $this->taux = $facturation->taux;
        $this->abonnement_montant = $facturation->abonnement_montant;
        $this->abonnement_periodicite = $facturation->abonnement_periodicite ?: 'mensuel';
        $this->abonnement_echeance = $facturation->abonnement_echeance?->toDateString() ?: '';
        $this->actif = $facturation->actif;
        $this->produitsSelectionnes = $facturation->produits->pluck('product_id')->all();

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->facturationId = null;
        $this->resetValidation();
    }

    public function save()
    {
        $valide = $this->validate(BoutiqueFacturation::regles(), [
            'required' => 'Cette valeur est obligatoire',
            'required_if' => 'Cette valeur est obligatoire pour ce mode',
            'numeric' => 'Entrez un nombre',
            'integer' => 'Entrez un nombre entier',
            'min' => 'La valeur ne peut pas être négative',
            'max' => 'Un pourcentage ne peut pas dépasser 100',
            'exists' => 'Boutique introuvable',
        ]);

        /*
         | Une boutique majorée sur « une sélection » sans aucun produit coché
         | ne majore rien du tout : le réglage serait enregistré sans effet, et
         | on croirait percevoir une commission qui n'existe pas.
         */
        if ($valide['mode'] === BoutiqueFacturation::MODE_COMMISSION
            && $valide['portee'] === BoutiqueFacturation::PORTEE_PRODUITS
            && empty($this->produitsSelectionnes)) {
            $this->addError('produitsSelectionnes', 'Cochez au moins un produit à majorer.');

            return;
        }

        // Une seule facturation par boutique (contrainte d'unicité en base) :
        // le signaler ici plutôt que de laisser remonter une erreur SQL.
        $doublon = BoutiqueFacturation::where('shop_id', $valide['shop_id'])
            ->when($this->facturationId, fn ($q) => $q->where('id', '!=', $this->facturationId))
            ->exists();

        if ($doublon) {
            $this->addError('shop_id', 'Cette boutique a déjà une facturation. Modifiez-la plutôt.');

            return;
        }

        \DB::transaction(function () use ($valide) {
            $donnees = $valide + ['actif' => (bool) $this->actif];

            // Les champs de l'autre mode sont remis à null : garder un taux sur
            // un abonnement laisserait croire, à la relecture, que les deux
            // s'appliquent.
            if ($donnees['mode'] === BoutiqueFacturation::MODE_ABONNEMENT) {
                $donnees['taux'] = null;
                $donnees['portee'] = BoutiqueFacturation::PORTEE_BOUTIQUE;
            } else {
                $donnees['abonnement_montant'] = null;
                $donnees['abonnement_periodicite'] = null;
                $donnees['abonnement_echeance'] = null;
            }

            $facturation = $this->facturationId
                ? tap(BoutiqueFacturation::findOrFail($this->facturationId))->update($donnees)
                : BoutiqueFacturation::create($donnees);

            $facturation->produits()->delete();

            if ($facturation->mode === BoutiqueFacturation::MODE_COMMISSION
                && $facturation->portee === BoutiqueFacturation::PORTEE_PRODUITS) {
                foreach ($this->produitsSelectionnes as $produitId) {
                    $facturation->produits()->create([
                        'product_id' => (int) $produitId,
                        // Taux propre au produit laissé vide : il reprend celui
                        // de la boutique. L'exception se règle produit par
                        // produit une fois la ligne créée.
                        'taux' => null,
                    ]);
                }
            }
        });

        $this->dispatch('notify', [
            'message' => $this->facturationId ? 'Facturation mise à jour.' : 'Facturation enregistrée.',
            'type' => 'success',
        ]);

        $this->closeModal();
    }

    public function basculerActif($id)
    {
        $facturation = BoutiqueFacturation::findOrFail($id);
        $facturation->update(['actif' => ! $facturation->actif]);

        $this->dispatch('notify', [
            'message' => $facturation->actif
                ? 'Facturation réactivée : elle s\'applique de nouveau.'
                : 'Facturation suspendue : plus aucune majoration sur cette boutique.',
            'type' => 'success',
        ]);
    }

    public function supprimer($id)
    {
        BoutiqueFacturation::findOrFail($id)->delete();

        $this->dispatch('notify', ['message' => 'Facturation supprimée.', 'type' => 'success']);
    }
};
?>

<x-layouts.app title="Finance">
    @volt
        <div>
            <x-ui.page-header
                title="Finance"
                subtitle="Commissions et abonnements des boutiques hébergées">
                <x-slot:actions>
                    <x-ui.button wire:click="openModal">Nouvelle facturation</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat
                    label="Boutiques à la commission"
                    :value="$this->resume['commissions']"
                    :hint="'taux moyen ' . $this->resume['taux_moyen'] . ' %'"
                    tone="brand"
                    icon="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />

                <x-ui.stat
                    label="Boutiques à l'abonnement"
                    :value="$this->resume['abonnements']"
                    hint="montant propre à chacune"
                    tone="accent"
                    icon="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />

                <x-ui.stat
                    label="Abonnements, au mois"
                    :value="number_format($this->resume['mensuel'], 0, ',', ' ') . ' F'"
                    hint="toutes périodicités ramenées au mois"
                    tone="success"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat
                    label="Échéances proches"
                    :value="$this->resume['echeances']"
                    hint="à 3 jours ou déjà dépassées"
                    :tone="$this->resume['echeances'] > 0 ? 'danger' : 'success'"
                    icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </div>

            <div class="mt-6">
                <x-ui.table
                    target="save,supprimer,basculerActif"
                    :headers="['Boutique', 'Mode', 'Portée', 'Taux / Montant', 'Échéance', 'Statut', 'Actions']">
                    @forelse ($this->facturations as $facturation)
                        @php
                            $estAbonnement = $facturation->mode === \App\Models\BoutiqueFacturation::MODE_ABONNEMENT;
                            $jours = $facturation->joursAvantEcheance();
                        @endphp

                        <tr @class([
                            'transition-colors hover:bg-gray-50',
                            'opacity-60' => ! $facturation->actif,
                        ])>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $facturation->shop?->shop_name ?? '—' }}</p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$estAbonnement ? 'info' : 'brand'">
                                    {{ $estAbonnement ? 'Abonnement' : 'Commission' }}
                                </x-ui.badge>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                @if ($estAbonnement)
                                    —
                                @elseif ($facturation->portee === \App\Models\BoutiqueFacturation::PORTEE_PRODUITS)
                                    {{ $facturation->produits->count() }} produit(s)
                                @else
                                    Toute la boutique
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                @if ($estAbonnement)
                                    {{ number_format((int) $facturation->abonnement_montant, 0, ',', ' ') }} F
                                    <span class="text-xs font-normal text-gray-400">/ {{ $facturation->abonnement_periodicite }}</span>
                                @else
                                    {{ rtrim(rtrim(number_format((float) $facturation->taux, 2, ',', ''), '0'), ',') }} %
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @if (! $estAbonnement || ! $facturation->abonnement_echeance)
                                    <span class="text-gray-400">—</span>
                                @else
                                    <p class="text-gray-900">{{ $facturation->abonnement_echeance->format('d/m/Y') }}</p>
                                    {{-- Le préavis se lit là où on lit l'échéance : sans lui,
                                         une date seule ne dit pas qu'elle est dépassée. --}}
                                    @if ($jours !== null && $jours <= \App\Models\BoutiqueFacturation::PREAVIS_JOURS)
                                        <p @class([
                                            'text-xs font-bold',
                                            'text-red-700' => $jours < 0,
                                            'text-amber-700' => $jours >= 0,
                                        ])>
                                            {{ $jours < 0 ? 'Échu depuis ' . abs($jours) . ' j' : ($jours === 0 ? "Aujourd'hui" : 'Dans ' . $jours . ' j') }}
                                        </p>
                                    @endif
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$facturation->actif ? 'success' : 'gray'">
                                    {{ $facturation->actif ? 'Active' : 'Suspendue' }}
                                </x-ui.badge>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.button size="sm" variant="secondary" wire:click="openModal({{ $facturation->id }})">
                                        Modifier
                                    </x-ui.button>

                                    <x-ui.button size="sm" :variant="$facturation->actif ? 'ghost' : 'success'"
                                                 wire:click="basculerActif({{ $facturation->id }})"
                                                 wire:confirm="{{ $facturation->actif ? 'Suspendre cette facturation ? Plus aucune majoration ne sera appliquée.' : 'Réactiver cette facturation ?' }}">
                                        {{ $facturation->actif ? 'Suspendre' : 'Réactiver' }}
                                    </x-ui.button>

                                    <x-ui.button size="sm" variant="danger"
                                                 wire:click="supprimer({{ $facturation->id }})"
                                                 wire:confirm="Supprimer définitivement cette facturation ?">
                                        Supprimer
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty
                            :colspan="7"
                            title="Aucune facturation"
                            message="Aucune boutique n'est facturée pour l'instant : ni commission, ni abonnement." />
                    @endforelse
                </x-ui.table>
            </div>

            <x-ui.modal
                :show="$showModal"
                :title="$facturationId ? 'Modifier la facturation' : 'Nouvelle facturation'"
                width="max-w-3xl">
                <form id="financeForm" wire:submit.prevent="save" class="space-y-5">
                    <x-ui.field label="Boutique" for="shop_id" :required="true"
                                :error="$errors->first('shop_id')">
                        <select id="shop_id" wire:model.live="shop_id"
                                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            <option value="">— Choisir une boutique —</option>
                            @foreach ($this->boutiques as $boutique)
                                <option value="{{ $boutique->id }}">{{ $boutique->shop_name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Mode de facturation" :required="true" :error="$errors->first('mode')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors',
                                'border-brand-500 bg-brand-50' => $mode === 'commission',
                                'border-gray-200 hover:bg-gray-50' => $mode !== 'commission',
                            ])>
                                <input type="radio" value="commission" wire:model.live="mode" class="mt-1 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Commission</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">
                                        Les prix affichés au client sont majorés. Le marchand garde son prix de base.
                                    </span>
                                </span>
                            </label>

                            <label @class([
                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors',
                                'border-brand-500 bg-brand-50' => $mode === 'abonnement',
                                'border-gray-200 hover:bg-gray-50' => $mode !== 'abonnement',
                            ])>
                                <input type="radio" value="abonnement" wire:model.live="mode" class="mt-1 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Abonnement</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">
                                        Aucune majoration. Montant propre à la boutique, rappelé 3 jours avant l'échéance.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </x-ui.field>

                    @if ($mode === 'commission')
                        <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-ui.field label="Taux de majoration" for="taux" :required="true"
                                            hint="% ajouté au prix, arrondi au multiple de 50 F"
                                            :error="$errors->first('taux')">
                                    <x-ui.input id="taux" type="number" min="0" max="100" step="0.01"
                                                wire:model="taux" :error="$errors->has('taux')" />
                                </x-ui.field>

                                <x-ui.field label="Portée" for="portee" :required="true"
                                            :error="$errors->first('portee')">
                                    <select id="portee" wire:model.live="portee"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                        <option value="boutique">Toute la boutique</option>
                                        <option value="produits">Une sélection de produits</option>
                                    </select>
                                </x-ui.field>
                            </div>

                            @if ($portee === 'produits')
                                <div>
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">
                                        Produits majorés
                                    </p>

                                    @if ($this->produitsBoutique->isEmpty())
                                        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500">
                                            Choisissez d'abord une boutique ayant des produits publiés.
                                        </p>
                                    @else
                                        <div class="max-h-56 space-y-1 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2">
                                            @foreach ($this->produitsBoutique as $produit)
                                                <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                                                    <input type="checkbox" value="{{ $produit->id }}"
                                                           wire:model="produitsSelectionnes"
                                                           class="rounded text-brand-600 focus:ring-brand-500">
                                                    <span class="flex-1 truncate text-gray-800">{{ $produit->name }}</span>
                                                    <span class="shrink-0 tabular-nums text-xs text-gray-500">
                                                        {{ number_format((float) $produit->price, 0, ',', ' ') }} F
                                                        @if ($taux !== '' && (float) $taux > 0)
                                                            <span class="font-bold text-brand-700">
                                                                → {{ number_format(\App\Support\MajorationBoutique::arrondi((float) $produit->price * (1 + (float) $taux / 100)), 0, ',', ' ') }} F
                                                            </span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    @error('produitsSelectionnes')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="grid gap-4 rounded-xl border border-gray-200 bg-gray-50/60 p-4 sm:grid-cols-3">
                            <x-ui.field label="Montant" for="abonnement_montant" :required="true"
                                        hint="en F CFA, propre à cette boutique"
                                        :error="$errors->first('abonnement_montant')">
                                <x-ui.input id="abonnement_montant" type="number" min="0"
                                            wire:model="abonnement_montant"
                                            :error="$errors->has('abonnement_montant')" />
                            </x-ui.field>

                            <x-ui.field label="Périodicité" for="abonnement_periodicite" :required="true"
                                        :error="$errors->first('abonnement_periodicite')">
                                <select id="abonnement_periodicite" wire:model="abonnement_periodicite"
                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                                    @foreach (array_keys(\App\Models\BoutiqueFacturation::PERIODICITES) as $periodicite)
                                        <option value="{{ $periodicite }}">{{ ucfirst($periodicite) }}</option>
                                    @endforeach
                                </select>
                            </x-ui.field>

                            <x-ui.field label="Prochaine échéance" for="abonnement_echeance" :required="true"
                                        hint="le marchand est averti 3 jours avant"
                                        :error="$errors->first('abonnement_echeance')">
                                <x-ui.input id="abonnement_echeance" type="date"
                                            wire:model="abonnement_echeance"
                                            :error="$errors->has('abonnement_echeance')" />
                            </x-ui.field>
                        </div>
                    @endif

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="actif" class="rounded text-brand-600 focus:ring-brand-500">
                        Facturation active
                    </label>
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="financeForm" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Enregistrer</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.app>
