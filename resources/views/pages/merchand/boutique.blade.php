<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Shop;

name('merchand.boutique');

/*
| Fiche de la boutique, modifiable par son responsable.
|
| Le marchand ne pilote que sa vitrine : nom, coordonnées, description, logo.
| Le type, le statut et le rattachement du responsable restent du ressort de
| l'équipe Poulet AFC — les exposer ici permettrait à un marchand de réactiver
| une boutique désactivée ou de se rattacher ailleurs.
*/
new class extends Component {
    use WithFileUploads;

    public $shop_name = '';
    public $city = '';
    public $address = '';
    public $phone1 = '';
    public $phone2 = '';
    public $email1 = '';
    public $email2 = '';
    public $description = '';
    public $logo = null;

    public function mount(): void
    {
        $boutique = $this->boutique;

        $this->shop_name = $boutique->shop_name;
        $this->city = $boutique->city;
        $this->address = $boutique->address;
        $this->phone1 = $boutique->phone1;
        $this->phone2 = $boutique->phone2;
        $this->email1 = $boutique->email1;
        $this->email2 = $boutique->email2;
        $this->description = $boutique->description;
    }

    public function getBoutiqueProperty(): Shop
    {
        return Shop::where('id_user', auth()->id())->firstOrFail();
    }

    public function save()
    {
        $valide = $this->validate([
            'shop_name' => 'required|string|max:191',
            'city' => 'nullable|string|max:191',
            'address' => 'nullable|string|max:191',
            'phone1' => 'nullable|string|max:30',
            'phone2' => 'nullable|string|max:30',
            'email1' => 'nullable|email|max:191',
            'email2' => 'nullable|email|max:191',
            'description' => 'nullable|string|max:2000',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $donnees = collect($valide)->except('logo')->all();

        if ($this->logo) {
            $nom = hexdec(uniqid()) . '.' . $this->logo->getClientOriginalExtension();
            $this->logo->storeAs('', $nom, 'uploads');
            $donnees['logo'] = asset('upload/' . $nom);
        }

        $this->boutique->update($donnees);
        $this->logo = null;

        $this->dispatch('notify', ['message' => 'Boutique mise à jour !', 'type' => 'success']);
    }
};
?>

<x-layouts.merchand title="Ma boutique">
    @volt
        <div class="max-w-3xl">
            <x-ui.page-header title="Ma boutique" subtitle="Informations visibles par vos clients" />

            <x-ui.card>
                <form wire:submit.prevent="save" class="space-y-5">

                    <div class="flex flex-wrap items-center gap-4 rounded-xl bg-gray-50 p-4">
                        <div class="shrink-0">
                            @if ($logo && ! is_string($logo))
                                <img src="{{ $logo->temporaryUrl() }}" alt="Aperçu"
                                     wire:loading.remove wire:target="logo"
                                     class="h-20 w-20 rounded-xl border-2 border-emerald-500 object-cover">
                            @elseif ($this->boutique->logo)
                                <img src="{{ $this->boutique->logo }}" alt="Logo"
                                     class="h-20 w-20 rounded-xl border border-gray-200 object-cover">
                            @else
                                <div class="flex h-20 w-20 items-center justify-center rounded-xl bg-emerald-100 text-xl font-bold text-emerald-700">
                                    {{ strtoupper(substr($this->boutique->shop_name ?? 'B', 0, 2)) }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="mb-1 text-xs font-semibold text-gray-700">Logo de la boutique</p>
                            <input type="file" wire:model="logo" accept="image/*"
                                   class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100">

                            <div wire:loading wire:target="logo" class="mt-2 flex items-center gap-2 text-xs font-medium text-emerald-700">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                </svg>
                                <span x-data="{ p: 0 }" x-on:livewire-upload-progress.window="p = $event.detail.progress" x-text="'Envoi… ' + p + '%'"></span>
                            </div>

                            @error('logo')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <x-ui.field label="Nom de la boutique" for="shop_name" :required="true" :error="$errors->first('shop_name')">
                        <x-ui.input id="shop_name" wire:model="shop_name" :error="$errors->has('shop_name')" />
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

                    <x-ui.field label="Description" for="description" :error="$errors->first('description')">
                        <x-ui.textarea id="description" wire:model="description" rows="4" :error="$errors->has('description')" />
                    </x-ui.field>

                    <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500">
                            Type, statut et responsable sont gérés par l'équipe Poulet AFC.
                        </p>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="logo,save">
                            <span wire:loading.remove wire:target="save">Enregistrer</span>
                            <span wire:loading wire:target="save">Enregistrement…</span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Référence</p>
                    <p class="mt-1 font-mono text-sm text-gray-900">{{ $this->boutique->ref ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Type</p>
                    <p class="mt-1 text-sm text-gray-900">{{ $this->boutique->type ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Statut</p>
                    <p class="mt-1">
                        <x-ui.badge :tone="$this->boutique->status === 'Success' ? 'success' : 'gray'">
                            {{ $this->boutique->status === 'Success' ? 'Active' : 'Inactive' }}
                        </x-ui.badge>
                    </p>
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.merchand>
