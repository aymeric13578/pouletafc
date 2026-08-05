<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;

name('merchand.produits');

/*
| Catalogue du marchand.
|
| Chaque requête est ancrée sur la boutique du compte connecté. Le filtrage n'est
| pas seulement appliqué à la liste : les méthodes d'écriture le refont, sinon un
| identifiant forgé dans une requête Livewire permettrait de modifier le produit
| d'une autre boutique.
*/
new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $search = '';

    public $showModal = false;
    public $editMode = false;
    public $productId = null;

    public $name = '';
    public $id_category = '';
    public $price = '';
    public $stock_init = '';
    public $description = '';
    public $image = null;
    public $existing_image = '';

    public function getBoutiqueProperty(): Shop
    {
        return Shop::where('id_user', auth()->id())->firstOrFail();
    }

    public function getProduitsProperty()
    {
        return Product::where('id_shop', $this->boutique->id)
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->latest('id')
            ->paginate(10);
    }

    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Récupère un produit en imposant l'appartenance à la boutique du compte.
     * findOrFail seul accepterait n'importe quel identifiant.
     */
    protected function produitDeLaBoutique(int $id): Product
    {
        return Product::where('id_shop', $this->boutique->id)->findOrFail($id);
    }

    public function openModal($id = null)
    {
        $this->resetForm();
        $this->editMode = $id !== null;

        if ($id) {
            $produit = $this->produitDeLaBoutique($id);

            $this->productId = $produit->id;
            $this->name = $produit->name;
            $this->id_category = $produit->id_category;
            $this->price = $produit->price;
            $this->stock_init = $produit->stock_init;
            $this->description = $produit->description;
            $this->existing_image = $produit->img ?: $produit->product_image1;
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
        $this->productId = null;
        $this->name = '';
        $this->id_category = '';
        $this->price = '';
        $this->stock_init = '';
        $this->description = '';
        $this->image = null;
        $this->existing_image = '';
        $this->resetValidation();
    }

    public function save()
    {
        $valide = $this->validate([
            'name' => 'required|string|max:255',
            'id_category' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock_init' => 'required|integer|min:0',
            'description' => 'required|string',
            'image' => ($this->editMode ? 'nullable' : 'required') . '|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $donnees = collect($valide)->except('image')->all();
        $donnees['quantity'] = $donnees['stock_init'];
        // Pas de colonne "category" sur products : la catégorie tient dans
        // id_category, et "category" n'est qu'une relation.

        if ($this->image) {
            $nom = hexdec(uniqid()) . '.' . $this->image->getClientOriginalExtension();
            $this->image->storeAs('', $nom, 'uploads');
            // img et product_image1 doivent désigner le même fichier : la boutique
            // lit l'un, l'application mobile l'autre.
            $donnees['img'] = asset('upload/' . $nom);
            $donnees['product_image1'] = $donnees['img'];
        }

        if ($this->editMode) {
            $this->produitDeLaBoutique($this->productId)->update($donnees);
            $message = 'Produit modifié !';
        } else {
            $donnees['id_shop'] = $this->boutique->id;
            $donnees['ref'] = 'PROD-' . strtoupper(substr(uniqid(), -6));
            $donnees['slug'] = str($donnees['name'])->slug()->toString();
            // Un produit créé par un marchand attend la validation de l'équipe
            // avant d'apparaître au catalogue.
            $donnees['status'] = 'pending';
            Product::create($donnees);
            $message = 'Produit créé. Il sera visible après validation par Poulet AFC.';
        }

        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
        $this->closeModal();
    }

    public function deleteProduct($id)
    {
        $this->produitDeLaBoutique($id)->delete();
        $this->dispatch('notify', ['message' => 'Produit supprimé !', 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.merchand title="Mes produits">
    @volt
        <div>
            <x-ui.page-header title="Mes produits" subtitle="Catalogue de votre boutique">
                <x-slot:actions>
                    <x-ui.button wire:click="openModal">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nouveau produit
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            <x-ui.search model="search" placeholder="Rechercher un produit…" />

            <div class="mt-4">
                <x-ui.table target="search,gotoPage,previousPage,nextPage"
                    :headers="['Produit', 'Catégorie', 'Prix', 'Stock', 'Statut', 'Actions']">
                    @forelse ($this->produits as $produit)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ product_image_url($produit->img ?: $produit->product_image1) }}" alt=""
                                         class="h-11 w-11 shrink-0 rounded-lg border border-gray-200 object-cover">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-gray-900">{{ $produit->name }}</p>
                                        <p class="font-mono text-xs text-gray-400">{{ $produit->ref }}</p>
                                    </div>
                                </div>
                            </td>
                            {{-- category est une relation, pas une colonne : l'afficher directement
                                 imprimait le modèle Category sérialisé en JSON dans le tableau. --}}
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $produit->category?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-gray-900">
                                {{ number_format((int) $produit->price, 0, ',', ' ') }} F
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="tabular-nums {{ (int) $produit->stock_init < 10 ? 'font-bold text-red-600' : 'text-gray-700' }}">
                                    {{ (int) $produit->stock_init }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$produit->status === 'Success' ? 'success' : 'warning'">
                                    {{ $produit->status === 'Success' ? 'En ligne' : 'En attente' }}
                                </x-ui.badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.button size="sm" variant="secondary" wire:click="openModal({{ $produit->id }})">Modifier</x-ui.button>
                                    <x-ui.button size="sm" variant="danger" wire:click="deleteProduct({{ $produit->id }})"
                                                 wire:confirm="Supprimer définitivement ce produit ?">Supprimer</x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="6" title="Aucun produit"
                            message="Ajoutez votre premier produit pour qu'il apparaisse au catalogue." />
                    @endforelse
                </x-ui.table>

                @if ($this->produits->hasPages())
                    <div class="mt-4">{{ $this->produits->links() }}</div>
                @endif
            </div>

            <x-ui.modal :show="$showModal" :title="$editMode ? 'Modifier le produit' : 'Nouveau produit'" width="max-w-2xl">
                <form id="produitForm" wire:submit.prevent="save" class="space-y-4">
                    <x-ui.field label="Nom du produit" for="name" :required="true" :error="$errors->first('name')">
                        <x-ui.input id="name" wire:model="name" :error="$errors->has('name')" />
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Catégorie" for="id_category" :required="true" :error="$errors->first('id_category')">
                            <x-ui.select id="id_category" wire:model="id_category" :error="$errors->has('id_category')">
                                <option value="">Sélectionner</option>
                                @foreach ($this->categories as $categorie)
                                    <option value="{{ $categorie->id }}">{{ $categorie->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Prix (FCFA)" for="price" :required="true" :error="$errors->first('price')">
                            <x-ui.input id="price" type="number" step="1" wire:model="price" :error="$errors->has('price')" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Stock" for="stock_init" :required="true" :error="$errors->first('stock_init')">
                        <x-ui.input id="stock_init" type="number" wire:model="stock_init" :error="$errors->has('stock_init')" />
                    </x-ui.field>

                    <x-ui.field label="Description" for="description" :required="true" :error="$errors->first('description')">
                        <x-ui.textarea id="description" wire:model="description" rows="3" :error="$errors->has('description')" />
                    </x-ui.field>

                    <x-ui.field label="Photo du produit" for="image" :required="! $editMode" :error="$errors->first('image')">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0">
                                @if ($image && ! is_string($image))
                                    <img src="{{ $image->temporaryUrl() }}" alt="Aperçu"
                                         wire:loading.remove wire:target="image"
                                         class="h-20 w-20 rounded-xl border-2 border-emerald-500 object-cover">
                                @elseif ($existing_image)
                                    <img src="{{ product_image_url($existing_image) }}" alt="Photo actuelle"
                                         class="h-20 w-20 rounded-xl border border-gray-200 object-cover">
                                @else
                                    <div class="flex h-20 w-20 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 text-xs text-gray-400">Aucune</div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <input type="file" id="image" wire:model="image" accept="image/*"
                                       class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100">

                                <div wire:loading wire:target="image" class="mt-2 flex items-center gap-2 text-xs font-medium text-emerald-700">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                    </svg>
                                    <span x-data="{ p: 0 }" x-on:livewire-upload-progress.window="p = $event.detail.progress" x-text="'Envoi… ' + p + '%'"></span>
                                </div>

                                @if ($editMode)
                                    <p class="mt-2 text-xs text-gray-500">Choisir un fichier remplace la photo actuelle.</p>
                                @endif
                            </div>
                        </div>
                    </x-ui.field>

                    @unless ($editMode)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Votre produit sera visible au catalogue après validation par l'équipe Poulet AFC.
                        </p>
                    @endunless
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="produitForm" wire:loading.attr="disabled" wire:target="image,save">
                        <span wire:loading.remove wire:target="save">{{ $editMode ? 'Enregistrer' : 'Créer le produit' }}</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.merchand>
