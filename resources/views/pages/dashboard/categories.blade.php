<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

name('dashboard.categories');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $editMode = false;
    public $categoryId = null;
    public $name = '';
    public $image = null;
    public $existing_image = '';
    public $showImagePopup = false;
    public $currentImage = '';

    public function getCategoriesProperty()
    {
        return Category::when($this->search, function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('ref', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getTotalCategoriesProperty()
    {
        return Category::count();
    }

    public function getActiveCategoriesProperty()
    {
        return Category::where('status', 'Success')->count();
    }

    public function getProductsPerCategoryProperty()
    {
        return Product::whereHas('category')->count();
    }

    public function openModal($mode = 'add', $id = null)
    {
        $this->resetForm();
        $this->editMode = ($mode === 'edit');
        if ($this->editMode && $id) {
            $category = Category::findOrFail($id);
            $this->categoryId = $category->id;
            $this->name = $category->name;
            $this->existing_image = $category->image;
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
        $this->categoryId = null;
        $this->name = '';
        $this->image = null;
        $this->existing_image = '';
        $this->resetValidation();
    }

    public function toggleCategoryStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->status = $category->status === 'Success' ? 'failed' : 'Success';
        $category->save();
        $this->dispatch('notify', ['message' => 'Statut de la catégorie modifié avec succès !', 'type' => 'success']);
    }

    public function saveCategory()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:categories,name' . ($this->editMode ? ',' . $this->categoryId : ''),
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'ref' => sprintf('CAT-%s%s%s', now()->format('Y'), now()->format('m'), now()->format('d')),
        ];

        if ($this->image) {
            $path = $this->image->store('upload', 'public');
            $data['image'] = Storage::url($path);
        } elseif ($this->editMode && !$this->image && $this->existing_image) {
            $data['image'] = $this->existing_image;
        }

        if ($this->editMode) {
            $category = Category::findOrFail($this->categoryId);
            $category->update($data);
            $this->dispatch('notify', ['message' => 'Catégorie modifiée avec succès !', 'type' => 'success']);
        } else {
            $data['status'] = 'pending'; // Statut par défaut pour une nouvelle catégorie
            Category::create($data);
            $this->dispatch('notify', ['message' => 'Catégorie ajoutée avec succès !', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->image) {
            Storage::disk('public')->delete(str_replace(Storage::url(''), '', $category->image));
        }
        $category->delete();
        $this->dispatch('notify', ['message' => 'Catégorie supprimée avec succès !', 'type' => 'success']);
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
};
?>

<x-layouts.app>
    @volt
        <div class="container mx-auto px-2 mt-6">
            <!-- Notifications -->
            <div x-data x-on:notify.window="toastr[event.detail.type](event.detail.message)"></div>
            <!-- Barre de recherche -->
            <form class="flex items-center max-w-lg mx-auto mb-6">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher une catégorie" />
                </div>
                <button type="submit" class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Rechercher
                </button>
            </form>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Catégories</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalCategories }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Catégories Actives</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->activeCategories }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Produits par Catégorie</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->productsPerCategory }}</p>
                </div>
            </div>

            <!-- Bouton Ajouter -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal" class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter Catégorie</button>
            </div>

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Nom</th>
                            <th class="py-3 px-4 text-gray-800">Image</th>
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->categories as $category)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $category->id }}</td>
                                <td class="py-3 px-4">{{ $category->name }}</td>
                                <td class="py-3 px-4">
                                    @if($category->image)
                                        <button type="button" wire:click="openImagePopup('{{ $category->image }}')" class="bg-blue-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-blue-700">Voir l'image</button>
                                    @else
                                        <span class="text-xs text-gray-500">Aucune</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $category->status === 'Success' ? 'bg-green-100 text-green-800' : ($category->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ $category->status === 'Success' ? 'Actif' : ($category->status === 'failed' ? 'Désactivé' : 'En attente') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <button wire:click="openModal('edit', {{ $category->id }})" class="text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button wire:click="deleteCategory({{ $category->id }})" onclick="return confirm('Supprimer cette catégorie ?')" class="text-red-600 hover:text-red-800 mr-2" title="Supprimer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                    <button wire:click="toggleCategoryStatus({{ $category->id }})" class="text-gray-600 hover:text-gray-800" title="{{ $category->status === 'Success' ? 'Désactiver' : 'Activer' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if ($category->status === 'Success')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            @endif
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Popup pour visualiser l'image -->
            <div wire:model="showImagePopup" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showImagePopup ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-4 max-w-lg w-full">
                    <div class="flex justify-end">
                        <button wire:click="closeImagePopup" class="text-gray-600 hover:text-gray-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <img src="{{ $currentImage }}" alt="Image" class="w-full h-auto rounded-lg">
                </div>
            </div>

            <!-- Modal d'ajout/modification -->
            <div wire:model="showModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $editMode ? 'Modifier une Catégorie' : 'Ajouter une Catégorie' }}</h2>
                    <form id="categoryForm" wire:submit.prevent="saveCategory" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="name">Nom <span class="text-red-500">*</span></label>
                            <input type="text" id="name" wire:model="name" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="image">Image</label>
                            <input type="file" id="image" wire:model="image" class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                            @error('image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            @if ($existing_image)
                                <div class="mt-2">
                                    <img src="{{ $existing_image }}" alt="Image actuelle" class="w-16 h-16 rounded object-cover" />
                                </div>
                            @endif
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" form="categoryForm" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">{{ $editMode ? 'Modifier' : 'Ajouter' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Toastr -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
            <script>
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            </script>
        </div>
    @endvolt
</x-layouts.app>
