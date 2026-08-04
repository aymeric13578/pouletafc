<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;

name('dashboard.boutiques');

/*
| Gestion des boutiques.
|
| L'écran d'origine vivait dans l'ancien back-office Bootstrap, avec son propre
| habillage et ses propres conventions. Il est repris ici sur les composants du
| tableau de bord.
|
| Le rattachement d'un responsable est central : c'est ce lien qui décide vers
| quel espace un utilisateur est envoyé à la connexion, et donc quelle boutique
| il gère.
*/
new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';
    public $typeFiltre = '';

    public $showModal = false;
    public $editMode = false;
    public $shopId = null;

    public $shop_name = '';
    public $type = 'INDEPENDANT';
    public $id_user = '';
    public $city = '';
    public $address = '';
    public $phone1 = '';
    public $phone2 = '';
    public $email1 = '';
    public $email2 = '';
    public $description = '';
    public $commercial_register = '';
    public $logo = null;
    public $existing_logo = '';

    public function getBoutiquesProperty()
    {
        return Shop::with('user:id,name,role,phone')
            ->withCount(['produits'])
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('shop_name', 'like', $terme)
                        ->orWhere('ref', 'like', $terme)
                        ->orWhere('city', 'like', $terme)
                        ->orWhereHas('user', fn ($r) => $r->where('name', 'like', $terme));
                });
            })
            ->when($this->typeFiltre, fn ($q) => $q->where('type', $this->typeFiltre))
            ->orderBy('shop_name')
            ->paginate(10);
    }

    /** Comptes pouvant gérer une boutique. Les clients en sont exclus. */
    public function getResponsablesProperty()
    {
        return User::whereIn('role', ['merchand', 'employee_afc', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    public function getTypesProperty()
    {
        return Shop::whereNotNull('type')->distinct()->pluck('type')->filter()->values();
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => Shop::count(),
            'actives' => Shop::where('status', 'Success')->count(),
            'sans_responsable' => Shop::whereNull('id_user')->count(),
            'produits' => Product::count(),
        ];
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        $this->editMode = $id !== null;

        if ($id) {
            $boutique = Shop::findOrFail($id);

            $this->shopId = $boutique->id;
            $this->shop_name = $boutique->shop_name;
            $this->type = $boutique->type ?: 'INDEPENDANT';
            $this->id_user = $boutique->id_user;
            $this->city = $boutique->city;
            $this->address = $boutique->address;
            $this->phone1 = $boutique->phone1;
            $this->phone2 = $boutique->phone2;
            $this->email1 = $boutique->email1;
            $this->email2 = $boutique->email2;
            $this->description = $boutique->description;
            $this->commercial_register = $boutique->commercial_register;
            $this->existing_logo = $boutique->logo;
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editMode = false;
        $this->shopId = null;
        $this->shop_name = '';
        $this->type = 'INDEPENDANT';
        $this->id_user = '';
        $this->city = '';
        $this->address = '';
        $this->phone1 = '';
        $this->phone2 = '';
        $this->email1 = '';
        $this->email2 = '';
        $this->description = '';
        $this->commercial_register = '';
        $this->logo = null;
        $this->existing_logo = '';
        $this->resetValidation();
    }

    public function save()
    {
        $valide = $this->validate([
            'shop_name' => 'required|string|max:191',
            'type' => 'required|string|max:50',
            // Un responsable ne pilote qu'une boutique : deux rattachements
            // rendraient la redirection à la connexion ambiguë.
            'id_user' => 'nullable|exists:users,id|unique:shops,id_user' . ($this->editMode ? ',' . $this->shopId : ''),
            'city' => 'nullable|string|max:191',
            'address' => 'nullable|string|max:191',
            'phone1' => 'nullable|string|max:30',
            'phone2' => 'nullable|string|max:30',
            'email1' => 'nullable|email|max:191',
            'email2' => 'nullable|email|max:191',
            'description' => 'nullable|string|max:2000',
            'commercial_register' => 'nullable|string|max:191',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ], [
            'id_user.unique' => 'Ce responsable gère déjà une autre boutique.',
        ]);

        $donnees = collect($valide)->except('logo')->all();
        $donnees['id_user'] = $donnees['id_user'] ?: null;

        if ($this->logo) {
            // Même disque que les images produits : public/upload, seul dossier
            // réellement servi par le webserver.
            $nom = hexdec(uniqid()) . '.' . $this->logo->getClientOriginalExtension();
            $this->logo->storeAs('', $nom, 'uploads');
            $donnees['logo'] = asset('upload/' . $nom);
        }

        if ($this->editMode) {
            Shop::findOrFail($this->shopId)->update($donnees);
            $message = 'Boutique modifiée !';
        } else {
            $donnees['ref'] = 'SHOP-' . strtoupper(substr(uniqid(), -6));
            $donnees['slug'] = str($donnees['shop_name'])->slug()->toString();
            $donnees['status'] = 'Success';
            Shop::create($donnees);
            $message = 'Boutique créée !';
        }

        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
        $this->closeModal();
    }

    public function toggleStatus($id)
    {
        $boutique = Shop::findOrFail($id);
        $boutique->status = $boutique->status === 'Success' ? 'failed' : 'Success';
        $boutique->save();

        $this->dispatch('notify', [
            'message' => $boutique->status === 'Success' ? 'Boutique activée !' : 'Boutique désactivée !',
            'type' => 'success',
        ]);
    }

    public function deleteShop($id)
    {
        $boutique = Shop::withCount('produits')->findOrFail($id);

        // Supprimer une boutique qui porte des produits les rendrait orphelins et
        // invisibles au catalogue : on refuse plutôt que de casser silencieusement.
        if ($boutique->produits_count > 0) {
            $this->dispatch('notify', [
                'message' => "Impossible : cette boutique porte {$boutique->produits_count} produit(s). Réaffectez-les d'abord.",
                'type' => 'error',
            ]);

            return;
        }

        $boutique->delete();
        $this->dispatch('notify', ['message' => 'Boutique supprimée !', 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Boutiques">
    @volt
        <div>
            <x-ui.page-header title="Boutiques" subtitle="Points de vente et responsables rattachés">
                <x-slot:actions>
                    <x-ui.button wire:click="openModal">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nouvelle boutique
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Boutiques" :value="$this->stats['total']" tone="brand"
                    icon="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614" />

                <x-ui.stat label="Actives" :value="$this->stats['actives']" tone="success"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Sans responsable" :value="$this->stats['sans_responsable']"
                    hint="personne ne peut les gérer"
                    :tone="$this->stats['sans_responsable'] > 0 ? 'warning' : 'success'"
                    icon="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z" />

                <x-ui.stat label="Produits au total" :value="$this->stats['produits']" tone="accent"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Boutique, référence, ville ou responsable…" />

                <x-ui.select wire:model.live="typeFiltre" class="w-auto min-w-[12rem]">
                    <option value="">Tous les types</option>
                    @foreach ($this->types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,typeFiltre,gotoPage,previousPage,nextPage"
                    :headers="['Boutique', 'Responsable', 'Contact', 'Produits', 'Statut', 'Actions']">
                    @forelse ($this->boutiques as $boutique)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($boutique->logo)
                                        <img src="{{ $boutique->logo }}" alt=""
                                             class="h-11 w-11 shrink-0 rounded-xl border border-gray-200 object-cover">
                                    @else
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700">
                                            {{ strtoupper(substr($boutique->shop_name ?? '?', 0, 2)) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900">{{ $boutique->shop_name }}</p>
                                        <p class="text-xs text-gray-500">
                                            <span class="font-mono">{{ $boutique->ref ?: '—' }}</span>
                                            @if ($boutique->type) · {{ $boutique->type }} @endif
                                            @if ($boutique->city) · {{ $boutique->city }} @endif
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                @if ($boutique->user)
                                    <p class="font-medium text-gray-900">{{ $boutique->user->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $boutique->user->role }}</p>
                                @else
                                    <x-ui.badge tone="warning">Aucun responsable</x-ui.badge>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if ($boutique->phone1)<p class="font-mono">{{ $boutique->phone1 }}</p>@endif
                                @if ($boutique->email1)<p class="truncate text-xs">{{ $boutique->email1 }}</p>@endif
                                @if (! $boutique->phone1 && ! $boutique->email1)<span class="text-gray-300">—</span>@endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="text-lg font-bold tabular-nums text-gray-900">{{ $boutique->produits_count }}</span>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <button type="button" wire:click="toggleStatus({{ $boutique->id }})">
                                    <x-ui.badge :tone="$boutique->status === 'Success' ? 'success' : 'gray'">
                                        {{ $boutique->status === 'Success' ? 'Active' : 'Inactive' }}
                                    </x-ui.badge>
                                </button>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.button size="sm" variant="secondary" wire:click="openModal({{ $boutique->id }})">
                                        Modifier
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="danger"
                                                 wire:click="deleteShop({{ $boutique->id }})"
                                                 wire:confirm="Supprimer définitivement cette boutique ?">
                                        Supprimer
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="6" title="Aucune boutique"
                            message="Créez une boutique pour lui rattacher un responsable et des produits." />
                    @endforelse
                </x-ui.table>

                @if ($this->boutiques->hasPages())
                    <div class="mt-4">{{ $this->boutiques->links() }}</div>
                @endif
            </div>

            <x-ui.modal :show="$showModal"
                        :title="$editMode ? 'Modifier la boutique' : 'Nouvelle boutique'"
                        subtitle="Le responsable rattaché sera redirigé vers cette boutique à sa connexion"
                        width="max-w-3xl">
                <form id="boutiqueForm" wire:submit.prevent="save" class="space-y-5">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Nom de la boutique" for="shop_name" :required="true" :error="$errors->first('shop_name')">
                            <x-ui.input id="shop_name" wire:model="shop_name" :error="$errors->has('shop_name')" />
                        </x-ui.field>

                        <x-ui.field label="Type" for="type" :required="true" :error="$errors->first('type')">
                            <x-ui.select id="type" wire:model="type" :error="$errors->has('type')">
                                <option value="AFC">AFC</option>
                                <option value="INDEPENDANT">Indépendant</option>
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Responsable" for="id_user" :error="$errors->first('id_user')"
                                hint="Il gérera cette boutique et y sera redirigé après connexion">
                        <x-ui.select id="id_user" wire:model="id_user" :error="$errors->has('id_user')">
                            <option value="">Aucun responsable</option>
                            @foreach ($this->responsables as $responsable)
                                <option value="{{ $responsable->id }}">{{ $responsable->name }} — {{ $responsable->role }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Ville" for="city" :error="$errors->first('city')">
                            <x-ui.input id="city" wire:model="city" :error="$errors->has('city')" />
                        </x-ui.field>

                        <x-ui.field label="Adresse" for="address" :error="$errors->first('address')">
                            <x-ui.input id="address" wire:model="address" :error="$errors->has('address')" />
                        </x-ui.field>

                        <x-ui.field label="Téléphone principal" for="phone1" :error="$errors->first('phone1')">
                            <x-ui.input id="phone1" wire:model="phone1" :error="$errors->has('phone1')" />
                        </x-ui.field>

                        <x-ui.field label="Téléphone secondaire" for="phone2" :error="$errors->first('phone2')">
                            <x-ui.input id="phone2" wire:model="phone2" :error="$errors->has('phone2')" />
                        </x-ui.field>

                        <x-ui.field label="E-mail principal" for="email1" :error="$errors->first('email1')">
                            <x-ui.input id="email1" type="email" wire:model="email1" :error="$errors->has('email1')" />
                        </x-ui.field>

                        <x-ui.field label="E-mail secondaire" for="email2" :error="$errors->first('email2')">
                            <x-ui.input id="email2" type="email" wire:model="email2" :error="$errors->has('email2')" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Registre de commerce" for="commercial_register" :error="$errors->first('commercial_register')">
                        <x-ui.input id="commercial_register" wire:model="commercial_register" :error="$errors->has('commercial_register')" />
                    </x-ui.field>

                    <x-ui.field label="Description" for="description" :error="$errors->first('description')">
                        <x-ui.textarea id="description" wire:model="description" rows="3" :error="$errors->has('description')" />
                    </x-ui.field>

                    <x-ui.field label="Logo" for="logo" :error="$errors->first('logo')">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0">
                                @if ($logo && ! is_string($logo))
                                    <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu"
                                         wire:loading.remove wire:target="logo"
                                         class="h-20 w-20 rounded-xl border-2 border-brand-500 object-cover">
                                @elseif ($existing_logo)
                                    <img src="{{ $existing_logo }}" alt="Logo actuel"
                                         class="h-20 w-20 rounded-xl border border-gray-200 object-cover">
                                @else
                                    <div class="flex h-20 w-20 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 text-xs text-gray-400">
                                        Aucun
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <input type="file" id="logo" wire:model="logo" accept="image/*"
                                       class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700 hover:file:bg-brand-100">

                                <div wire:loading wire:target="logo" class="mt-2 flex items-center gap-2 text-xs font-medium text-brand-700">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                    </svg>
                                    <span x-data="{ p: 0 }" x-on:livewire-upload-progress.window="p = $event.detail.progress"
                                          x-text="'Envoi… ' + p + '%'"></span>
                                </div>
                            </div>
                        </div>
                    </x-ui.field>
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="boutiqueForm"
                                 wire:loading.attr="disabled" wire:target="logo,save">
                        <span wire:loading.remove wire:target="save">{{ $editMode ? 'Enregistrer' : 'Créer la boutique' }}</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.app>
