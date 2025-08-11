<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Product;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

name('dashboard.products');

new class extends Component {
    use WithFileUploads;

    public $search = '';
    public $showModal = false;
    public $editMode = false;
    public $viewMode = false;
    public $showImagePopup = false;
    public $currentImage = '';
    public $productId = null;
    public $name = '';
    public $id_category = '';
    public $id_shop = '';
    public $price = '';
    public $stock_init = '';
    public $description = '';
    public $locality = '';
    public $bar_code = '';
    public $commission = '';
    public $product_image1 = null;
    public $product_image2 = null;
    public $existing_image1 = '';
    public $existing_image2 = '';
    public $product_length = '';
    public $product_width = '';
    public $product_epaisseur = '';
    public $product_volume = '';
    public $product_color = '';
    public $product_weight = '';
    public $parameter1 = '';
    public $parameter2 = '';
    public $category_name = '';
    public $shop_name = '';
    public $categories = [];
    public $shops = [];

    public function mount()
    {
        $this->categories = Category::all();
        $this->shops = Shop::all();
    }

    public function openModal($mode = 'add', $productId = null)
    {
        $this->resetForm();
        $this->editMode = ($mode === 'edit');
        $this->viewMode = ($mode === 'view');

        if (in_array($mode, ['edit', 'view']) && $productId) {
            $this->productId = $productId;
            $product = Product::findOrFail($productId);
            $this->name = $product->name;
            $this->id_category = $product->id_category;
            $this->id_shop = $product->id_shop;
            $this->price = $product->price;
            $this->stock_init = $product->stock_init;
            $this->description = $product->description;
            $this->locality = $product->locality;
            $this->bar_code = $product->bar_code;
            $this->commission = $product->commission;
            $this->product_length = $product->product_length;
            $this->product_width = $product->product_width;
            $this->product_epaisseur = $product->product_epaisseur;
            $this->product_volume = $product->product_volume;
            $this->product_color = $product->product_color;
            $this->product_weight = $product->product_weight ?? $product->product_weigth;
            $this->parameter1 = $product->parameter1;
            $this->parameter2 = $product->parameter2;
            $this->existing_image1 = $product->product_image1;
            $this->existing_image2 = $product->product_image2;
            $this->category_name = $product->category?->name ?? 'N/A';
            $this->shop_name = $product->shop?->shop_name ?? 'N/A';
        }

        $this->showModal = true;
    }

    public function openImagePopup($imageUrl)
    {
        $this->currentImage = $imageUrl;
        $this->showImagePopup = true;
    }

    public function closeImagePopup()
    {
        $this->showImagePopup = false;
        $this->currentImage = '';
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editMode = false;
        $this->viewMode = false;
        $this->showImagePopup = false;
        $this->currentImage = '';
        $this->productId = null;
        $this->name = '';
        $this->id_category = '';
        $this->id_shop = '';
        $this->price = '';
        $this->stock_init = '';
        $this->description = '';
        $this->locality = '';
        $this->bar_code = '';
        $this->commission = '';
        $this->product_image1 = null;
        $this->product_image2 = null;
        $this->existing_image1 = '';
        $this->existing_image2 = '';
        $this->product_length = '';
        $this->product_width = '';
        $this->product_epaisseur = '';
        $this->product_volume = '';
        $this->product_color = '';
        $this->product_weight = '';
        $this->parameter1 = '';
        $this->parameter2 = '';
        $this->category_name = '';
        $this->shop_name = '';
        $this->resetValidation();
    }

    public function addProduct()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:products,name' . ($this->editMode ? ',' . $this->productId : ''),
            'id_category' => 'required|exists:categories,id',
            'id_shop' => 'required|exists:shops,id',
            'price' => 'required|numeric|min:0',
            'stock_init' => 'required|integer|min:0',
            'description' => 'required|string',
            'locality' => 'nullable|string|max:191',
            'bar_code' => 'nullable|string|max:191',
            'commission' => 'nullable|string|max:191',
            'product_image1' => $this->editMode ? 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048' : 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'product_image2' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'product_length' => 'nullable|string|max:191',
            'product_width' => 'nullable|string|max:191',
            'product_epaisseur' => 'nullable|string|max:191',
            'product_volume' => 'nullable|string|max:191',
            'product_color' => 'nullable|string|max:191',
            'product_weight' => 'nullable|string|max:191',
            'parameter1' => 'nullable|string|max:255',
            'parameter2' => 'nullable|string|max:255',
        ]);

        $data = [
            'name' => $this->name,
            'id_category' => $this->id_category,
            'id_shop' => $this->id_shop,
            'price' => $this->price,
            'stock_init' => $this->stock_init,
            'description' => $this->description,
            'locality' => $this->locality,
            'bar_code' => $this->bar_code,
            'commission' => $this->commission,
            'product_length' => $this->product_length,
            'product_width' => $this->product_width,
            'product_epaisseur' => $this->product_epaisseur,
            'product_volume' => $this->product_volume,
            'product_color' => $this->product_color,
            'product_weight' => $this->product_weight,
            'parameter1' => $this->parameter1,
            'parameter2' => $this->parameter2,
            'ref' => sprintf('POULET-%s%s%s', now()->format('Y'), now()->format('m'), now()->format('d')),
            'status' => 'pending',
            'slug' => Str::slug($this->name),
        ];

        if ($this->product_image1) {
            $path = $this->product_image1->store('upload', 'public');
            $data['product_image1'] = Storage::url($path);
        }

        if ($this->product_image2) {
            $path = $this->product_image2->store('upload', 'public');
            $data['product_image2'] = Storage::url($path);
        }

        if ($this->editMode) {
            $product = Product::findOrFail($this->productId);
            $product->update($data);
            $this->dispatch('notify', ['message' => 'Produit modifié avec succès !', 'type' => 'success']);
        } else {
            $product = Product::create($data);
            Category::where('id', $this->id_category)->increment('product_count', 1);
            Shop::where('id', $this->id_shop)->increment('product_count', 1);
            $this->dispatch('notify', ['message' => 'Produit ajouté avec succès !', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function toggleStatus($productId)
    {
        $product = Product::findOrFail($productId);
        $newStatus = $product->status === 'Success' ? 'pending' : 'Success';
        $product->update(['status' => $newStatus]);
        $message = $newStatus === 'Success' ? 'Produit activé avec succès !' : 'Produit désactivé avec succès !';
        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
    }

    public function deleteProduct($productId)
    {
        $product = Product::findOrFail($productId);
        if ($product->product_image1) {
            Storage::disk('public')->delete(str_replace(Storage::url(''), '', $product->product_image1));
        }
        if ($product->product_image2) {
            Storage::disk('public')->delete(str_replace(Storage::url(''), '', $product->product_image2));
        }
        $product->update(['status' => 'failed']);
        Category::where('id', $product->id_category)->decrement('product_count', 1);
        Shop::where('id', $product->id_shop)->decrement('product_count', 1);
        $this->dispatch('notify', ['message' => 'Produit supprimé avec succès !', 'type' => 'success']);
    }

    public function getProductsProperty()
    {
        return Product::with(['category', 'shop'])
            ->where('status', '!=', 'failed')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')->orWhere('ref', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getTotalProductsProperty()
    {
        return Product::where('status', '!=', 'failed')->count();
    }

    public function getActiveProductsProperty()
    {
        return Product::where('status', 'Success')->count();
    }

    public function getLowStockProductsProperty()
    {
        return Product::where('status', '!=', 'failed')->where('stock_init', '<', 10)->count();
    }
};
?>

<x-layouts.app>
    @volt
        <div>
            <div class="container mx-auto px-2 mt-6">
                <!-- Notifications -->
                <div x-data x-on:notify.window="toastr[event.detail.type](event.detail.message)"></div>

                <!-- Barre de recherche -->
                <form class="flex items-center max-w-lg mx-auto mb-6">
                    <label for="search" class="sr-only">Rechercher</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                            </svg>
                        </div>
                        <input type="text" id="search" wire:model.live="search"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                            placeholder="Rechercher un produit" />
                    </div>
                    <button type="submit"
                        class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                        </svg>Rechercher
                    </button>
                </form>

                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Produits</h3>
                        <p class="text-3xl font-bold text-indigo-600">{{ $this->totalProducts }}</p>
                    </div>
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Produits Actifs</h3>
                        <p class="text-3xl font-bold text-indigo-600">{{ $this->activeProducts }}</p>
                    </div>
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Stock Faible</h3>
                        <p class="text-3xl font-bold text-indigo-600">{{ $this->lowStockProducts }}</p>
                    </div>
                </div>

                <!-- Bouton Ajouter -->
                <div class="flex justify-end mb-4">
                    <button wire:click="openModal('add')"
                        class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter
                        Produit</button>
                </div>

                <!-- Tableau des produits -->
                <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b">
                                <th class="py-3 px-4 text-gray-800">Référence</th>
                                <th class="py-3 px-4 text-gray-800">Nom</th>
                                <th class="py-3 px-4 text-gray-800">Catégorie</th>
                                <th class="py-3 px-4 text-gray-800">Prix (FCFA)</th>
                                <th class="py-3 px-4 text-gray-800">Stock</th>
                                <th class="py-3 px-4 text-gray-800">Statut</th>
                                <th class="py-3 px-4 text-gray-800">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->products as $product)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-3 px-4">{{ $product->ref }}</td>
                                    <td class="py-3 px-4">{{ $product->name }}</td>
                                    <td class="py-3 px-4">{{ $product->category?->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4">{{ number_format($product->price, 2, ',', ' ') }}</td>
                                    <td class="py-3 px-4">{{ $product->stock_init }}</td>
                                    <td class="py-3 px-4">
                                        @if ($product->status === 'Success')
                                            <span
                                                class="inline-block px-2 py-1 rounded-full bg-green-100 text-green-800 text-xs">Actif</span>
                                        @elseif ($product->status === 'pending')
                                            <span
                                                class="inline-block px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs">En
                                                attente</span>
                                        @else
                                            <span
                                                class="inline-block px-2 py-1 rounded-full bg-red-100 text-red-800 text-xs">Suspendu</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <button wire:click="openModal('edit', {{ $product->id }})"
                                            class="text-indigo-600 hover:text-indigo-800 mr-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button wire:click="openModal('view', {{ $product->id }})"
                                            class="text-blue-600 hover:text-blue-800 mr-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button wire:click="toggleStatus({{ $product->id }})"
                                            class="text-purple-600 hover:text-purple-800 mr-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </button>
                                        <button wire:click="deleteProduct({{ $product->id }})"
                                            onclick="return confirm('Voulez-vous supprimer ce produit ?')"
                                            class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Modale pour création/modification/visualisation -->
                <div wire:model="showModal"
                     class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                    <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">
                            {{ $viewMode ? 'Visualiser le Produit' : ($editMode ? 'Modifier un Produit' : 'Ajouter un Produit') }}
                        </h2>
                        @if ($viewMode)
                            <div class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Nom</label>
                                        <p class="text-sm">{{ $name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Catégorie</label>
                                        <p class="text-sm">{{ $category_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Boutique</label>
                                        <p class="text-sm">{{ $shop_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Prix (FCFA)</label>
                                        <p class="text-sm">{{ $price }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Stock</label>
                                        <p class="text-sm">{{ $stock_init }}</p>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1">Description</label>
                                        <p class="text-sm">{{ $description }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Localité</label>
                                        <p class="text-sm">{{ $locality }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Code barre</label>
                                        <p class="text-sm">{{ $bar_code }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Commission</label>
                                        <p class="text-sm">{{ $commission }}</p>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1">Images</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                @if ($existing_image1)
                                                    <button type="button" wire:click="openImagePopup('{{ $existing_image1 }}')"
                                                        class="bg-blue-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-blue-700">Voir l'image 1</button>
                                                @else
                                                    <p class="text-sm">Aucune image</p>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($existing_image2)
                                                    <button type="button" wire:click="openImagePopup('{{ $existing_image2 }}')"
                                                        class="bg-blue-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-blue-700">Voir l'image 2</button>
                                                @else
                                                    <p class="text-sm">Aucune image</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Longueur</label>
                                        <p class="text-sm">{{ $product_length }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Largeur</label>
                                        <p class="text-sm">{{ $product_width }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Épaisseur</label>
                                        <p class="text-sm">{{ $product_epaisseur }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Volume</label>
                                        <p class="text-sm">{{ $product_volume }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Couleur</label>
                                        <p class="text-sm">{{ $product_color }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Poids</label>
                                        <p class="text-sm">{{ $product_weight }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Paramètre 1</label>
                                        <p class="text-sm">{{ $parameter1 }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Paramètre 2</label>
                                        <p class="text-sm">{{ $parameter2 }}</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <form id="productForm" wire:submit.prevent="addProduct" class="space-y-4">
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="name">Nom <span class="text-red-500">*</span></label>
                                        <input type="text" id="name" wire:model="name"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                        @error('name')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="id_category">Catégorie <span class="text-red-500">*</span></label>
                                        <select id="id_category" wire:model="id_category" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                            <option value="">Sélectionner</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('id_category')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="id_shop">Boutique <span class="text-red-500">*</span></label>
                                        <select id="id_shop" wire:model="id_shop" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                            <option value="">Sélectionner</option>
                                            @foreach ($shops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->shop_name }}</option>
                                            @endforeach
                                        </select>
                                        @error('id_shop')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="price">Prix (FCFA) <span class="text-red-500">*</span></label>
                                        <input type="number" id="price" wire:model="price" step="0.01"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                        @error('price')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="stock_init">Stock <span class="text-red-500">*</span></label>
                                        <input type="number" id="stock_init" wire:model="stock_init"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                        @error('stock_init')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1" for="description">Description <span class="text-red-500">*</span></label>
                                        <textarea id="description" wire:model="description" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required></textarea>
                                        @error('description')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="locality">Localité</label>
                                        <input type="text" id="locality" wire:model="locality"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('locality')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="bar_code">Code barre</label>
                                        <input type="text" id="bar_code" wire:model="bar_code"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('bar_code')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="commission">Commission</label>
                                        <input type="text" id="commission" wire:model="commission"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('commission')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1">Images</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-gray-700 text-sm mb-1" for="product_image1">Image 1 @unless($editMode)<span class="text-red-500">*</span>@endunless</label>
                                                <input type="file" id="product_image1" wire:model="product_image1"
                                                       class="w-full p-1 text-sm border border-gray-300 rounded-lg" {{ $editMode ? '' : 'required' }}>
                                                @error('product_image1')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                                @if ($existing_image1)
                                                    <button type="button" wire:click="openImagePopup('{{ $existing_image1 }}')"
                                                        class="mt-2 bg-blue-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-blue-700">Voir l'image 1</button>
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-gray-700 text-sm mb-1" for="product_image2">Image 2 (optionnel)</label>
                                                <input type="file" id="product_image2" wire:model="product_image2"
                                                       class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                                                @error('product_image2')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                                @if ($existing_image2)
                                                    <button type="button" wire:click="openImagePopup('{{ $existing_image2 }}')"
                                                        class="mt-2 bg-blue-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-blue-700">Voir l'image 2</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_length">Longueur</label>
                                        <input type="text" id="product_length" wire:model="product_length"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_length')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_width">Largeur</label>
                                        <input type="text" id="product_width" wire:model="product_width"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_width')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_epaisseur">Épaisseur</label>
                                        <input type="text" id="product_epaisseur" wire:model="product_epaisseur"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_epaisseur')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_volume">Volume</label>
                                        <input type="text" id="product_volume" wire:model="product_volume"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_volume')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_color">Couleur</label>
                                        <input type="text" id="product_color" wire:model="product_color"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_color')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="product_weight">Poids</label>
                                        <input type="text" id="product_weight" wire:model="product_weight"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('product_weight')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="parameter1">Paramètre 1</label>
                                        <input type="text" id="parameter1" wire:model="parameter1"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('parameter1')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1" for="parameter2">Paramètre 2</label>
                                        <input type="text" id="parameter2" wire:model="parameter2"
                                               class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                        @error('parameter2')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </form>
                        @endif
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal"
                                    class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Fermer</button>
                            @if (!$viewMode)
                                <button type="submit" form="productForm"
                                        class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">{{ $editMode ? 'Modifier' : 'Ajouter' }}</button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Popup pour visualiser l'image -->
                <div wire:model="showImagePopup"
                     class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showImagePopup ? '' : 'hidden' }}">
                    <div class="bg-white rounded-lg p-4 max-w-lg w-full">
                        <div class="flex justify-end">
                            <button wire:click="closeImagePopup"
                                    class="text-gray-600 hover:text-gray-800">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        <img src="{{ $currentImage }}" alt="Image du produit" class="w-full h-auto rounded-lg">
                    </div>
                </div>

                <!-- Inclure toastr -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
                <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
                <script>
                    toastr.options = {
                        closeButton: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        timeOut: 3000,
                    };
                </script>
            </div>
        </div>
    @endvolt
</x-layouts.app>
