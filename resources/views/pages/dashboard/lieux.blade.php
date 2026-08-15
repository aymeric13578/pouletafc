<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Location;
use App\Models\Quarter;

name('dashboard.lieux');

/*
| Lieux et quartiers enregistrés depuis l'application mobile.
|
| Les agents saisissent un lieu sur le terrain, rattaché à un quartier — un
| quartier en compte plusieurs. Ces données alimentent la recherche d'adresse de
| livraison du panier (endpoint getLocations), mais aucun écran ne permettait de
| les consulter ni de les corriger : un lieu mal nommé ou sans coordonnées
| restait tel quel, invisible, tout en étant proposé aux clients.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $quartierFiltre = '';
    public $sansCoordonnees = false;

    public $showModal = false;
    public $locationId = null;
    public $name = '';
    public $id_quarter = '';
    public $longitude = '';
    public $latitude = '';

    public function getQuartiersProperty()
    {
        return Quarter::withCount('locations')->orderBy('name')->get();
    }

    public function getLieuxProperty()
    {
        return Location::with(['quarter:id,name', 'user:id,name,role,phone'])
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('name', 'like', $terme)
                        ->orWhereHas('quarter', fn ($r) => $r->where('name', 'like', $terme))
                        ->orWhereHas('user', fn ($r) => $r->where('name', 'like', $terme));
                });
            })
            ->when($this->quartierFiltre, fn ($q) => $q->where('id_quarter', $this->quartierFiltre))
            // Un lieu sans coordonnées ne peut pas servir à calculer une livraison :
            // il est proposé au client puis échoue plus loin. D'où ce filtre dédié.
            ->when($this->sansCoordonnees, fn ($q) => $q->where(function ($sub) {
                $sub->whereNull('latitude')->orWhereNull('longitude')
                    ->orWhere('latitude', '')->orWhere('longitude', '');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Lieu servant de point de livraison par défaut.
     *
     * Quand le client ne choisit pas sa zone, le point retombait sur la
     * dernière position connue de son compte — vide ou héritée pour un compte
     * jamais localisé, donc identique pour tout le monde et parfois à des
     * centaines de kilomètres. Ce lieu-ci sert de repli, en dernier recours
     * seulement : une position réelle du client reste toujours prioritaire.
     */
    public function getLieuParDefautProperty(): ?int
    {
        $id = \App\Models\Parameter::active()?->default_pickup_location_id;

        // Le lieu a pu être supprimé depuis sa désignation : on ne suppose pas
        // qu'il existe encore.
        return $id && Location::whereKey($id)->exists() ? (int) $id : null;
    }

    /**
     * Désigne ce lieu comme point de livraison par défaut, ou le retire.
     */
    public function definirParDefaut($id): void
    {
        $grille = \App\Models\Parameter::active();

        if (! $grille) {
            $this->dispatch('notify', [
                'message' => "Activez d'abord une configuration.",
                'type' => 'error',
            ]);

            return;
        }

        $lieu = Location::find($id);

        if (! $lieu) {
            $this->dispatch('notify', ['message' => 'Ce lieu n\'existe plus.', 'type' => 'error']);

            return;
        }

        // Un lieu sans coordonnées enverrait le livreur au large du golfe de
        // Guinée : le refuser ici vaut mieux que de le découvrir à la livraison.
        if (blank($lieu->latitude) || blank($lieu->longitude)) {
            $this->dispatch('notify', [
                'message' => 'Ce lieu n\'a pas de coordonnées : renseignez-les d\'abord.',
                'type' => 'error',
            ]);

            return;
        }

        $retrait = $this->lieuParDefaut === (int) $id;

        $grille->update(['default_pickup_location_id' => $retrait ? null : $lieu->id]);

        $this->dispatch('notify', [
            'message' => $retrait
                ? 'Point de livraison par défaut retiré.'
                : $lieu->name . ' est désormais le point de livraison par défaut.',
            'type' => 'success',
        ]);
    }

    public function getStatsProperty(): array
    {
        $sansCoord = Location::where(function ($q) {
            $q->whereNull('latitude')->orWhereNull('longitude')
              ->orWhere('latitude', '')->orWhere('longitude', '');
        })->count();

        return [
            'quartiers' => Quarter::count(),
            'lieux' => Location::count(),
            'sans_coordonnees' => $sansCoord,
            'contributeurs' => Location::whereNotNull('id_user')->distinct('id_user')->count('id_user'),
            // Nom du lieu retenu, ou null : l'écran doit pouvoir le dire sans
            // parcourir les pages du tableau.
            'defaut' => $this->lieuParDefaut
                ? Location::whereKey($this->lieuParDefaut)->value('name')
                : null,
        ];
    }

    public function openModal($id)
    {
        $lieu = Location::findOrFail($id);

        $this->locationId = $lieu->id;
        $this->name = $lieu->name;
        $this->id_quarter = $lieu->id_quarter;
        $this->longitude = $lieu->longitude;
        $this->latitude = $lieu->latitude;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->locationId = null;
        $this->resetValidation();
    }

    public function save()
    {
        $valide = $this->validate([
            'name' => 'required|string|max:191',
            'id_quarter' => 'required|exists:quarters,id',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
        ]);

        Location::findOrFail($this->locationId)->update($valide);

        $this->dispatch('notify', ['message' => 'Lieu mis à jour !', 'type' => 'success']);
        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $lieu = Location::findOrFail($id);
        $lieu->status = $lieu->status === 'Success' ? 'pending' : 'Success';
        $lieu->save();

        $this->dispatch('notify', [
            'message' => $lieu->status === 'Success' ? 'Lieu activé !' : 'Lieu désactivé !',
            'type' => 'success',
        ]);
    }

    public function deleteLocation($id)
    {
        Location::findOrFail($id)->delete();
        $this->dispatch('notify', ['message' => 'Lieu supprimé !', 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedQuartierFiltre()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Lieux et quartiers">
    @volt
        <div>
            <x-ui.page-header
                title="Lieux et quartiers"
                subtitle="Enregistrés sur le terrain depuis l'application mobile" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat
                    label="Quartiers"
                    :value="$this->stats['quartiers']"
                    tone="brand"
                    icon="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" />

                <x-ui.stat
                    label="Lieux"
                    :value="$this->stats['lieux']"
                    tone="accent"
                    icon="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />

                <x-ui.stat
                    label="Sans coordonnées"
                    :value="$this->stats['sans_coordonnees']"
                    hint="inutilisables pour livrer"
                    :tone="$this->stats['sans_coordonnees'] > 0 ? 'warning' : 'success'"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />

                <x-ui.stat
                    label="Contributeurs"
                    :value="$this->stats['contributeurs']"
                    hint="agents et employés"
                    tone="success"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </div>

            {{--
              | Point de livraison par défaut.
              |
              | Rappelé ici en clair : le bouton vit dans le tableau, et sur
              | plusieurs pages de lieux on ne saurait sinon lequel est retenu
              | sans les parcourir tous.
            --}}
            <div class="mt-4 rounded-2xl border px-5 py-4 {{ $this->stats['defaut'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                @if ($this->stats['defaut'])
                    <p class="text-sm font-bold text-emerald-900">
                        ★ Point de livraison par défaut : {{ $this->stats['defaut'] }}
                    </p>
                    <p class="mt-1 text-xs text-emerald-800">
                        Utilisé lorsqu'un client ne choisit pas sa zone de livraison. Une position
                        réelle du client reste prioritaire ; ce lieu n'est qu'un dernier recours.
                    </p>
                @else
                    <p class="text-sm font-bold text-amber-900">Aucun point de livraison par défaut</p>
                    <p class="mt-1 text-xs text-amber-800">
                        Quand un client ne choisit pas sa zone, la livraison retombe sur la dernière
                        position connue de son compte — souvent vide, et alors identique pour tout le
                        monde. Choisissez un lieu ci-dessous avec « Définir par défaut ».
                    </p>
                @endif
            </div>

            {{-- Filtres --}}
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Lieu, quartier ou agent…" />

                <x-ui.select wire:model.live="quartierFiltre" class="w-auto min-w-[14rem]">
                    <option value="">Tous les quartiers</option>
                    @foreach ($this->quartiers as $quartier)
                        <option value="{{ $quartier->id }}">{{ $quartier->name }} ({{ $quartier->locations_count }})</option>
                    @endforeach
                </x-ui.select>

                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                    <input type="checkbox" wire:model.live="sansCoordonnees"
                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Sans coordonnées seulement
                </label>
            </div>

            <div class="mt-4">
                <x-ui.table
                    target="search,quartierFiltre,sansCoordonnees,gotoPage,previousPage,nextPage"
                    :headers="['Lieu', 'Quartier', 'Enregistré par', 'Coordonnées', 'Statut', 'Par défaut', 'Actions']">
                    @forelse ($this->lieux as $lieu)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $lieu->name }}</p>
                                <p class="text-xs text-gray-400">{{ $lieu->created_at?->format('d/m/Y') }}</p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($lieu->quarter)
                                    <x-ui.badge tone="brand">{{ $lieu->quarter->name }}</x-ui.badge>
                                @else
                                    {{-- Certains lieux pointent vers un quartier supprimé. --}}
                                    <x-ui.badge tone="danger">Quartier introuvable</x-ui.badge>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($lieu->user)
                                    <p class="font-medium text-gray-900">{{ $lieu->user->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $lieu->user->role }}{{ $lieu->user->phone ? ' · ' . $lieu->user->phone : '' }}
                                    </p>
                                @else
                                    <span class="text-sm text-gray-400">Inconnu</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @if (filled($lieu->latitude) && filled($lieu->longitude))
                                    <a href="https://www.google.com/maps?q={{ $lieu->latitude }},{{ $lieu->longitude }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="font-mono text-xs text-brand-700 hover:underline">
                                        {{ round((float) $lieu->latitude, 5) }}, {{ round((float) $lieu->longitude, 5) }}
                                    </a>
                                @else
                                    <x-ui.badge tone="warning">Manquantes</x-ui.badge>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <button type="button" wire:click="toggleStatus({{ $lieu->id }})">
                                    <x-ui.badge :tone="$lieu->status === 'Success' ? 'success' : 'gray'">
                                        {{ $lieu->status === 'Success' ? 'Actif' : 'Inactif' }}
                                    </x-ui.badge>
                                </button>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @php $estDefaut = $this->lieuParDefaut === $lieu->id; @endphp
                                @if (blank($lieu->latitude) || blank($lieu->longitude))
                                    {{-- Sans coordonnées, ce lieu ne peut servir de repli. --}}
                                    <span class="text-xs text-gray-400">Coordonnées requises</span>
                                @elseif ($estDefaut)
                                    <button type="button" wire:click="definirParDefaut({{ $lieu->id }})"
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300 hover:bg-emerald-200"
                                            title="Cliquer pour retirer">
                                        ★ Par défaut
                                    </button>
                                @else
                                    <button type="button" wire:click="definirParDefaut({{ $lieu->id }})"
                                            class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                                        Définir par défaut
                                    </button>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.button size="sm" variant="secondary" wire:click="openModal({{ $lieu->id }})">
                                        Modifier
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="danger"
                                                 wire:click="deleteLocation({{ $lieu->id }})"
                                                 wire:confirm="Supprimer définitivement ce lieu ?">
                                        Supprimer
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty
                            :colspan="7"
                            title="Aucun lieu"
                            message="Les lieux enregistrés par les agents depuis l'application mobile apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->lieux->hasPages())
                    <div class="mt-4">{{ $this->lieux->links() }}</div>
                @endif
            </div>

            {{-- Modification d'un lieu --}}
            <x-ui.modal :show="$showModal" title="Modifier le lieu" width="max-w-xl">
                <form id="lieuForm" wire:submit.prevent="save" class="space-y-4">
                    <x-ui.field label="Nom du lieu" for="name" :required="true" :error="$errors->first('name')">
                        <x-ui.input id="name" wire:model="name" :error="$errors->has('name')" />
                    </x-ui.field>

                    <x-ui.field label="Quartier" for="id_quarter" :required="true" :error="$errors->first('id_quarter')">
                        <x-ui.select id="id_quarter" wire:model="id_quarter" :error="$errors->has('id_quarter')">
                            <option value="">Sélectionner</option>
                            @foreach ($this->quartiers as $quartier)
                                <option value="{{ $quartier->id }}">{{ $quartier->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Latitude" for="latitude" :error="$errors->first('latitude')"
                                    hint="Entre -90 et 90">
                            <x-ui.input id="latitude" wire:model="latitude" :error="$errors->has('latitude')" />
                        </x-ui.field>

                        <x-ui.field label="Longitude" for="longitude" :error="$errors->first('longitude')"
                                    hint="Entre -180 et 180">
                            <x-ui.input id="longitude" wire:model="longitude" :error="$errors->has('longitude')" />
                        </x-ui.field>
                    </div>

                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Sans coordonnées, le lieu reste proposé au client mais la livraison ne peut pas être calculée.
                    </p>
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="lieuForm" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Enregistrer</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.app>
