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
    /*
    | Une fiche produit porte quatre colonnes d'image, mais le formulaire n'en
    | pilotait que deux (product_image1 et product_image2) et jamais "img".
    | Or la boutique se sert de "img" comme image principale et construit sa
    | galerie à partir des quatre : remplacer l'image depuis le tableau de bord
    | laissait donc l'ancienne en place ET ajoutait la nouvelle à côté.
    |
    | Le formulaire distingue maintenant explicitement :
    |   image principale  -> img + product_image1 (les deux, pour que la boutique
    |                        et l'application mobile pointent sur la même)
    |   images secondaires -> product_image2, product_image3
    */
    public $main_image = null;
    public $secondary_image1 = null;
    public $secondary_image2 = null;

    public $existing_main = '';
    public $existing_secondary1 = '';
    public $existing_secondary2 = '';

    /** Images existantes marquées pour suppression à l'enregistrement. */
    public $remove_secondary1 = false;
    public $remove_secondary2 = false;
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
    /*
     | categories et shops étaient des propriétés publiques contenant des collections
     | Eloquent complètes. Livewire sérialise l'état public du composant à CHAQUE
     | requête — frappe clavier, ouverture de modale, et surtout chaque morceau de
     | fichier envoyé : ces deux tables faisaient l'aller-retour à chaque fois.
     | En propriétés calculées, elles sont lues une fois par rendu et ne transitent
     | plus par le réseau.
     */
    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    public function getShopsProperty()
    {
        return Shop::orderBy('shop_name')->get(['id', 'shop_name']);
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
            $this->is_complement = (bool) $product->is_complement;
            $this->product_length = $product->product_length;
            $this->product_width = $product->product_width;
            $this->product_epaisseur = $product->product_epaisseur;
            $this->product_volume = $product->product_volume;
            $this->product_color = $product->product_color;
            $this->product_weight = $product->product_weight ?? $product->product_weigth;
            $this->parameter1 = $product->parameter1;
            $this->parameter2 = $product->parameter2;
            // "img" fait foi comme image principale ; on retombe sur product_image1
            // pour les fiches créées avant que le formulaire ne pilote "img".
            $this->existing_main = $product->img ?: $product->product_image1;
            $this->existing_secondary1 = $product->product_image2;
            $this->existing_secondary2 = $product->product_image3;
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

    /*
    | Ce produit peut-il accompagner un autre ?
    |
    | Se règle ici plutôt que sur un second écran : c'est au moment où l'on crée
    | une portion de frites qu'on sait qu'elle sert d'accompagnement. Le produit
    | reste vendable seul — la case ne le retire pas du catalogue.
    */
    public $is_complement = false;

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
        $this->is_complement = false;
        $this->main_image = null;
        $this->secondary_image1 = null;
        $this->secondary_image2 = null;
        $this->existing_main = '';
        $this->existing_secondary1 = '';
        $this->existing_secondary2 = '';
        $this->remove_secondary1 = false;
        $this->remove_secondary2 = false;
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
            'main_image' => $this->editMode ? 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096' : 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'secondary_image1' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'secondary_image2' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
            'product_length' => 'nullable|string|max:191',
            'product_width' => 'nullable|string|max:191',
            'product_epaisseur' => 'nullable|string|max:191',
            'product_volume' => 'nullable|string|max:191',
            'product_color' => 'nullable|string|max:191',
            'product_weight' => 'nullable|string|max:191',
            'parameter1' => 'nullable|string|max:255',
            'parameter2' => 'nullable|string|max:255',
        ]);

        $category_name = Category::where('id', $this->id_category)->value('name');
        $ref = sprintf('POULET-%s%s%s', now()->format('Y'), now()->format('m'), now()->format('d'));

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'locality' => $this->locality,
            'stock_init' => $this->stock_init,
            'price' => $this->price,
            'category' => $category_name,
            'id_category' => $this->id_category,
            'id_shop' => $this->id_shop,
            'bar_code' => $this->bar_code,
            'commission' => $this->commission,
            'is_complement' => (bool) $this->is_complement,
            'product_length' => $this->product_length,
            'product_width' => $this->product_width,
            'product_epaisseur' => $this->product_epaisseur,
            'product_volume' => $this->product_volume,
            'product_color' => $this->product_color,
            'product_weight' => $this->product_weight,
            'parameter1' => $this->parameter1,
            'parameter2' => $this->parameter2,
            'quantity' => $this->stock_init,
            'slug' => strtolower(str_replace(' ', '-', $this->name)),
            'ref' => $ref,
            'status' => 'pending',
        ];

        // Image principale : écrite dans "img" ET "product_image1". La boutique lit
        // "img", l'application mobile "product_image1" : les deux doivent désigner
        // le même fichier, sinon l'ancienne image reste visible à côté de la neuve.
        if ($this->main_image) {
            $url = $this->storeUploadedImage($this->main_image);
            $data['img'] = $url;
            $data['product_image1'] = $url;
        }

        if ($this->secondary_image1) {
            $data['product_image2'] = $this->storeUploadedImage($this->secondary_image1);
        } elseif ($this->remove_secondary1) {
            $data['product_image2'] = null;
        }

        if ($this->secondary_image2) {
            $data['product_image3'] = $this->storeUploadedImage($this->secondary_image2);
        } elseif ($this->remove_secondary2) {
            $data['product_image3'] = null;
        }

        if ($this->editMode) {
            $product = Product::findOrFail($this->productId);

            // Supprime du disque les fichiers remplacés, sinon public/upload enfle
            // indéfiniment à chaque modification.
            if ($this->main_image) {
                $this->deleteStoredImage($product->img);
                if ($product->product_image1 !== $product->img) {
                    $this->deleteStoredImage($product->product_image1);
                }
            }
            if ($this->secondary_image1 || $this->remove_secondary1) {
                $this->deleteStoredImage($product->product_image2);
            }
            if ($this->secondary_image2 || $this->remove_secondary2) {
                $this->deleteStoredImage($product->product_image3);
            }

            $product->update($data);
            $this->dispatch('notify', ['message' => 'Produit modifié avec succès !', 'type' => 'success']);
        } else {
            $product = Product::create($data);
            $product->update(['ref' => $ref]);
            Category::where('id', $this->id_category)->increment('product_count', 1);
            Shop::where('id', $this->id_shop)->increment('product_count', 1);
            $this->dispatch('notify', ['message' => 'Produit ajouté avec succès !', 'type' => 'success']);
        }

        $this->closeModal();
    }

    /**
     * Enregistre une image envoyée depuis le formulaire et renvoie son URL publique.
     *
     * Deux pièges corrigés ici, qui rendaient toute image ajoutée invisible :
     *  - le fichier partait sur le disque "public", dont la racine se résout sur la
     *    racine du projet et non sur public/ : il atterrissait dans <projet>/upload,
     *    que le serveur web ne sert pas. On passe par le disque "uploads", qui pointe
     *    sur public/upload ;
     *  - l'URL était préfixée en dur par l'ancien domaine 2gether-network.com, qui ne
     *    répond plus. asset() suit APP_URL et suivra donc tout changement de domaine.
     */
    protected function storeUploadedImage($file): string
    {
        $name = hexdec(uniqid()) . '.' . $file->getClientOriginalExtension();
        $file->storeAs('', $name, 'uploads');

        return asset('upload/' . $name);
    }

    /**
     * Supprime le fichier correspondant à une URL enregistrée en base.
     *
     * Les valeurs stockées cohabitent sous trois formes selon leur époque : URL
     * absolue sur l'ancien domaine, URL absolue sur le domaine actuel, ou chemin
     * relatif. On ne garde que la partie chemin pour retrouver le fichier.
     *
     * Les images livrées avec le projet (images/produits de la boutique) ne sont
     * jamais supprimées : elles sont versionnées et partagées entre fiches.
     */
    protected function deleteStoredImage(?string $url): void
    {
        if (! $url) {
            return;
        }

        $path = ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/');

        if (! str_starts_with($path, 'upload/')) {
            return;
        }

        $full = public_path($path);

        if (is_file($full)) {
            @unlink($full);
        }
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
        foreach ([$product->img, $product->product_image1, $product->product_image2, $product->product_image3] as $imageUrl) {
            $this->deleteStoredImage($imageUrl);
        }
        $product->delete();
        Category::where('id', $product->id_category)->decrement('product_count', 1);
        Shop::where('id', $product->id_shop)->decrement('product_count', 1);
        $this->dispatch('notify', ['message' => 'Produit supprimé avec succès !', 'type' => 'success']);
    }

    /*
    | Sélection multiple.
    |
    | Rattacher un complément produit par produit devient vite pénible : une
    | sauce accompagne souvent tout un menu. On coche les plats concernés, on
    | choisit le complément, et le lien se fait en une fois.
    */
    public $selection = [];
    public $complementARattacher = '';

    public function getComplementsDisponiblesProperty()
    {
        return Product::where('is_complement', true)
            ->where('status', 'Success')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    public function toutSelectionner(): void
    {
        $this->selection = $this->products
            ->where('is_complement', false)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function viderSelection(): void
    {
        $this->selection = [];
    }

    public function rattacherComplement(): void
    {
        $ids = collect($this->selection)->filter()->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            $this->dispatch('notify', ['message' => 'Sélectionnez d\'abord des produits.', 'type' => 'error']);

            return;
        }

        $complement = Product::where('is_complement', true)->find($this->complementARattacher);

        if (! $complement) {
            $this->dispatch('notify', ['message' => 'Choisissez un complément à rattacher.', 'type' => 'error']);

            return;
        }

        // Un produit ne peut pas se proposer lui-même : l'écran de vente
        // l'afficherait sous lui-même.
        $cibles = $ids->reject(fn (int $id) => $id === (int) $complement->id);

        $deja = 0;
        $faits = 0;

        foreach ($cibles as $id) {
            $produit = Product::find($id);

            if (! $produit) {
                continue;
            }

            if ($produit->complements()->where('complement_id', $complement->id)->exists()) {
                $deja++;

                continue;
            }

            $produit->complements()->attach($complement->id);
            $faits++;
        }

        $this->selection = [];
        $this->complementARattacher = '';

        $this->dispatch('notify', [
            'message' => $faits === 0
                ? $complement->name . ' était déjà rattaché à ces produits.'
                : sprintf(
                    '%s rattaché à %d produit%s%s.',
                    $complement->name,
                    $faits,
                    $faits > 1 ? 's' : '',
                    $deja > 0 ? sprintf(' (%d l\'avaient déjà)', $deja) : ''
                ),
            'type' => 'success',
        ]);
    }

    /**
     * Désigne un produit comme complément depuis sa carte, ou le retire.
     */
    public function basculerComplement($id): void
    {
        $produit = Product::findOrFail($id);
        $devient = ! $produit->is_complement;

        $produit->update(['is_complement' => $devient]);

        if (! $devient) {
            // Ses rattachements deviendraient trompeurs : on les défait.
            $produit->proposePar()->detach();
        }

        $this->dispatch('notify', [
            'message' => $devient
                ? $produit->name . ' peut désormais être proposé en complément.'
                : $produit->name . " n'est plus un complément ; ses rattachements sont défaits.",
            'type' => 'success',
        ]);
    }

    public function getProductsProperty()
    {
        return Product::with(['category', 'shop', 'complements:id,name'])
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

                <!-- Actions -->
                <div class="flex flex-wrap items-center justify-end gap-3 mb-4">
                    <button wire:click="openModal('add')"
                        class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">
                        Ajouter Produit
                    </button>
                </div>

                {{--
                  | Rattachement groupé.
                  |
                  | N'apparaît qu'une fois des produits cochés : une barre
                  | permanente encombrerait l'écran pour un geste occasionnel.
                --}}
                @if (count($selection) > 0)
                    <div wire:key="rattachement-groupe"
                         class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4">
                        <span class="text-sm font-bold text-indigo-900">
                            {{ count($selection) }} produit{{ count($selection) > 1 ? 's' : '' }} sélectionné{{ count($selection) > 1 ? 's' : '' }}
                        </span>

                        <select wire:model="complementARattacher"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Choisir un complément…</option>
                            @foreach ($this->complementsDisponibles as $complement)
                                <option value="{{ $complement->id }}">
                                    {{ $complement->name }} — {{ number_format((int) $complement->price, 0, ',', ' ') }} F
                                </option>
                            @endforeach
                        </select>

                        <button type="button" wire:click="rattacherComplement"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">
                            Rattacher
                        </button>

                        <button type="button" wire:click="viderSelection"
                                class="text-xs font-semibold text-gray-600 hover:underline">
                            Tout décocher
                        </button>

                        @if ($this->complementsDisponibles->isEmpty())
                            <span class="text-xs text-amber-800">
                                Aucun complément disponible : cochez « complément » sur un produit d'abord.
                            </span>
                        @endif
                    </div>
                @else
                    <div class="mb-4 flex justify-end">
                        <button type="button" wire:click="toutSelectionner"
                                class="text-xs font-semibold text-indigo-600 hover:underline">
                            Sélectionner tous les produits affichés
                        </button>
                    </div>
                @endif

                <!-- Produits en cartes -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @forelse ($this->products as $product)
                        @php
                            $coche = in_array((string) $product->id, $selection, true);
                            $image = $product->product_image1 ?: $product->img;
                        @endphp

                        <div wire:key="produit-{{ $product->id }}"
                             class="flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition
                                    {{ $coche ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200 hover:shadow-md' }}">

                            <div class="relative h-36 bg-gray-100">
                                @if ($image)
                                    <img src="{{ asset('upload/' . $image) }}" alt="{{ $product->name }}"
                                         class="h-full w-full object-cover"
                                         onerror="this.style.display='none'">
                                @else
                                    <div class="flex h-full items-center justify-center text-xs text-gray-400">
                                        Sans photo
                                    </div>
                                @endif

                                {{-- La case ne s'affiche que sur les produits ordinaires :
                                     un complément ne se rattache pas à lui-même. --}}
                                @unless ($product->is_complement)
                                    <label class="absolute left-2 top-2 flex cursor-pointer items-center rounded-lg bg-white/90 p-1.5 shadow">
                                        <input type="checkbox" wire:model.live="selection" value="{{ $product->id }}"
                                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </label>
                                @endunless

                                <div class="absolute right-2 top-2 flex flex-col items-end gap-1">
                                    @if ($product->is_complement)
                                        <span class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white">
                                            Complément
                                        </span>
                                    @endif
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold text-white
                                        {{ $product->status === 'Success' ? 'bg-emerald-600' : 'bg-gray-500' }}">
                                        {{ $product->status === 'Success' ? 'En vente' : 'Retiré' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $product->category?->name ?? 'Sans catégorie' }}
                                    @if ($product->shop) · {{ $product->shop->shop_name }} @endif
                                </p>

                                <div class="mt-2 flex items-baseline justify-between">
                                    <span class="text-lg font-bold text-indigo-700">
                                        {{ number_format((int) $product->price, 0, ',', ' ') }} F
                                    </span>
                                    <span class="text-xs {{ (int) $product->stock_init < 10 ? 'font-bold text-red-600' : 'text-gray-500' }}">
                                        stock {{ (int) $product->stock_init }}
                                    </span>
                                </div>

                                @if ($product->complements->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($product->complements as $lie)
                                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] text-indigo-700">
                                                + {{ $lie->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-auto flex flex-wrap items-center gap-2 pt-3">
                                    <button wire:click="openModal('edit', {{ $product->id }})"
                                            class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        Modifier
                                    </button>
                                    <button wire:click="openModal('view', {{ $product->id }})"
                                            class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        Voir
                                    </button>
                                    <button wire:click="toggleStatus({{ $product->id }})"
                                            class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        {{ $product->status === 'Success' ? 'Retirer' : 'Remettre' }}
                                    </button>
                                    <button wire:click="basculerComplement({{ $product->id }})"
                                            class="rounded-lg px-2.5 py-1 text-xs font-semibold
                                                {{ $product->is_complement
                                                    ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                                    : 'border border-amber-400 text-amber-700 hover:bg-amber-50' }}">
                                        {{ $product->is_complement ? 'Ne plus proposer' : 'Ajouter un complément' }}
                                    </button>
                                    <button wire:click="deleteProduct({{ $product->id }})"
                                            wire:confirm="Supprimer définitivement ce produit ?"
                                            class="rounded-lg border border-red-300 px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center">
                            <p class="font-semibold text-gray-700">Aucun produit</p>
                            <p class="mt-1 text-sm text-gray-500">Aucun produit ne correspond à cette recherche.</p>
                        </div>
                    @endforelse
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
                                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-500">Images</label>
                                        <div class="flex flex-wrap items-end gap-4">
                                            <div>
                                                <p class="mb-1 text-xs font-semibold text-gray-600">Principale</p>
                                                @if ($existing_main)
                                                    <button type="button" wire:click="openImagePopup('{{ $existing_main }}')">
                                                        <img src="{{ $existing_main }}" alt="Image principale"
                                                             class="h-24 w-24 rounded-xl border-2 border-brand-200 object-cover transition hover:opacity-80">
                                                    </button>
                                                @else
                                                    <div class="flex h-24 w-24 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 text-xs text-gray-400">Aucune</div>
                                                @endif
                                            </div>

                                            @foreach ([$existing_secondary1, $existing_secondary2] as $i => $secondaire)
                                                <div>
                                                    <p class="mb-1 text-xs font-semibold text-gray-600">Secondaire {{ $i + 1 }}</p>
                                                    @if ($secondaire)
                                                        <button type="button" wire:click="openImagePopup('{{ $secondaire }}')">
                                                            <img src="{{ $secondaire }}" alt="Image secondaire {{ $i + 1 }}"
                                                                 class="h-16 w-16 rounded-lg border border-gray-200 object-cover transition hover:opacity-80">
                                                        </button>
                                                    @else
                                                        <div class="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-xs text-gray-400">—</div>
                                                    @endif
                                                </div>
                                            @endforeach
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
                                            @foreach ($this->categories as $category)
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
                                            @foreach ($this->shops as $shop)
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

                                    {{--
                                      | Complément : se règle ici plutôt que sur un second écran.
                                      | C'est au moment où l'on crée une portion de frites qu'on
                                      | sait qu'elle sert d'accompagnement.
                                    --}}
                                    <div class="col-span-3">
                                        <label for="is_complement"
                                               class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-300 p-2">
                                            <input type="checkbox" id="is_complement" wire:model="is_complement"
                                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>
                                                <span class="block text-sm font-medium text-gray-800">
                                                    Ce produit peut accompagner un autre
                                                </span>
                                                <span class="block text-xs text-gray-500">
                                                    Il sera proposé en complément — frites, boisson, sauce. Il reste
                                                    vendable seul ; le rattachement aux plats se fait dans « Compléments ».
                                                </span>
                                            </span>
                                        </label>
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
                                        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Image principale @unless($editMode)<span class="text-red-500">*</span>@endunless</p>
                                            <p class="mt-0.5 text-xs text-gray-500">Celle qui représente le produit dans la boutique, l'application et les partages.</p>

                                            <div class="mt-3 flex flex-wrap items-start gap-4">
                                                <div class="shrink-0">
                                                    @if ($main_image && ! is_string($main_image))
                                                        <img src="{{ $main_image->temporaryUrl() }}" alt="Nouvelle image principale"
                                                             wire:loading.remove wire:target="main_image"
                                                             class="h-24 w-24 rounded-xl border-2 border-brand-500 object-cover shadow-sm">
                                                    @elseif ($existing_main)
                                                        <button type="button" wire:click="openImagePopup('{{ $existing_main }}')" class="block">
                                                            <img src="{{ $existing_main }}" alt="Image principale actuelle"
                                                                 class="h-24 w-24 rounded-xl border border-gray-200 object-cover transition hover:opacity-80">
                                                        </button>
                                                    @else
                                                        <div class="flex h-24 w-24 items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-7 w-7 text-gray-300">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 6.75h.008v.008H18V6.75z" />
                                                            </svg>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="min-w-0 flex-1">
                                                    <input type="file" id="main_image" wire:model="main_image" accept="image/*"
                                                           class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-700 hover:file:bg-brand-100">

                                                    <div wire:loading wire:target="main_image" class="mt-2 flex items-center gap-2 text-xs font-medium text-brand-700">
                                                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                                        </svg>
                                                        <span x-data="{ p: 0 }" x-on:livewire-upload-progress.window="p = $event.detail.progress"
                                                              x-text="'Envoi… ' + p + '%'"></span>
                                                    </div>

                                                    @if ($editMode && $existing_main && ! $main_image)
                                                        <p class="mt-2 text-xs text-gray-500">Choisir un fichier <strong>remplace</strong> l'image actuelle.</p>
                                                    @endif

                                                    @error('main_image')
                                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-xl border border-gray-200 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Images secondaires</p>
                                            <p class="mt-0.5 text-xs text-gray-500">Facultatives. Affichées dans la galerie de la fiche produit.</p>

                                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                                @foreach ([
                                                    ['secondary_image1', 'existing_secondary1', 'remove_secondary1', 'Image secondaire 1'],
                                                    ['secondary_image2', 'existing_secondary2', 'remove_secondary2', 'Image secondaire 2'],
                                                ] as [$champ, $existant, $suppression, $libelle])
                                                    <div class="flex items-start gap-3">
                                                        <div class="shrink-0">
                                                            @if ($$champ && ! is_string($$champ))
                                                                <img src="{{ $$champ->temporaryUrl() }}" alt="{{ $libelle }}"
                                                                     wire:loading.remove wire:target="{{ $champ }}"
                                                                     class="h-16 w-16 rounded-lg border-2 border-brand-500 object-cover">
                                                            @elseif ($$existant && ! $$suppression)
                                                                <button type="button" wire:click="openImagePopup('{{ $$existant }}')" class="block">
                                                                    <img src="{{ $$existant }}" alt="{{ $libelle }}"
                                                                         class="h-16 w-16 rounded-lg border border-gray-200 object-cover transition hover:opacity-80">
                                                                </button>
                                                            @else
                                                                <div class="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-gray-300">
                                                                    <span class="text-lg text-gray-300">+</span>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <label class="mb-1 block text-xs font-semibold text-gray-700" for="{{ $champ }}">{{ $libelle }}</label>
                                                            <input type="file" id="{{ $champ }}" wire:model="{{ $champ }}" accept="image/*"
                                                                   class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white p-1.5 text-xs file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-gray-700">

                                                            <div wire:loading wire:target="{{ $champ }}" class="mt-1.5 flex items-center gap-1.5 text-xs text-brand-700">
                                                                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                                                                </svg>
                                                                Envoi…
                                                            </div>

                                                            @if ($$existant && ! $$suppression && ! $$champ)
                                                                <button type="button" wire:click="$set('{{ $suppression }}', true)"
                                                                        class="mt-1.5 text-xs font-semibold text-red-600 hover:underline">Retirer</button>
                                                            @elseif ($$suppression)
                                                                <p class="mt-1.5 text-xs text-red-600">Sera retirée à l'enregistrement.
                                                                    <button type="button" wire:click="$set('{{ $suppression }}', false)" class="font-semibold underline">Annuler</button>
                                                                </p>
                                                            @endif

                                                            @error($champ)
                                                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                @endforeach
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
                                {{-- Désactivé pendant l'envoi d'une image et pendant l'enregistrement :
                                     un clic prématuré validait le formulaire sans l'image, ou créait un doublon. --}}
                                <button type="submit" form="productForm"
                                        wire:loading.attr="disabled" wire:target="main_image,secondary_image1,secondary_image2,addProduct"
                                        class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="addProduct">{{ $editMode ? 'Modifier' : 'Ajouter' }}</span>
                                    <span wire:loading wire:target="addProduct">Enregistrement…</span>
                                </button>
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