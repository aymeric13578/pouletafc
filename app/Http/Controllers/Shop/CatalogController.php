<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageDePartage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->catalogue($request, null);
    }

    /**
     * Le catalogue restreint à une catégorie, sous une adresse à elle.
     *
     * Même écran que le catalogue : ce qui change est l'adresse — partageable,
     * lisible, stable — et l'aperçu que les messageries en tirent.
     */
    public function categorie(Request $request, string $slug): Response
    {
        return $this->catalogue($request, Category::where('slug', $slug)->firstOrFail());
    }

    private function catalogue(Request $request, ?Category $categorie): Response
    {
        $query = Product::where('status', 'Success');

        if ($categorie) {
            $query->where('id_category', $categorie->id);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! $categorie && $categoryId = $request->integer('category')) {
            $query->where('id_category', $categoryId);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->input('max_price'));
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest' => $query->orderBy('id', 'desc'),
            default => $query->orderBy('id', 'desc'), // "popularité" par défaut : derniers ajouts
        };

        $products = $query->paginate(12)->withQueryString();

        $products->getCollection()->transform(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (int) $product->price,
            'image_url' => product_image_url($product->img ?: $product->product_image1),
        ]);

        $categories = Category::orderBy('name')->get(['id', 'name', 'slug']);

        $rendu = Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['q', 'category', 'min_price', 'max_price', 'sort']),
            'category' => $categorie ? $this->partageDeCategorie($categorie, $products->total()) : null,
        ]);

        if (! $categorie) {
            return $rendu;
        }

        return $rendu->withViewData(['meta' => [
            'title' => $categorie->name . ' — Poulet AFC',
            'description' => $this->descriptionDeCategorie($categorie, $products->total()),
            'image' => route('shop.share.image.category', $categorie->slug),
            'image_width' => ImageDePartage::LARGEUR,
            'image_height' => ImageDePartage::HAUTEUR,
            'url' => route('shop.catalog.category', $categorie->slug),
        ]]);
    }

    /**
     * Ce dont l'écran a besoin pour proposer le partage de la catégorie.
     */
    private function partageDeCategorie(Category $categorie, int $nombre): array
    {
        return [
            'id' => $categorie->id,
            'name' => $categorie->name,
            'slug' => $categorie->slug,
            'share_url' => route('shop.catalog.category', $categorie->slug),
            'share_description' => $this->descriptionDeCategorie($categorie, $nombre),
        ];
    }

    private function descriptionDeCategorie(Category $categorie, int $nombre): string
    {
        $produits = $nombre > 1 ? $nombre . ' produits' : ($nombre === 1 ? '1 produit' : 'Nos produits');

        return $produits . ' de la catégorie ' . $categorie->name . ' sur Poulet AFC, livrés chez vous.';
    }

    public function show(string $slug): Response
    {
        $product = Product::where('status', 'Success')->where('slug', $slug)->firstOrFail();

        $gallery = collect([
            $product->img,
            $product->product_image1,
            $product->product_image2,
            $product->product_image3,
        ])->filter()->unique()->map(fn ($path) => product_image_url($path))->values();

        if ($gallery->isEmpty()) {
            $gallery->push(product_image_url(null));
        }

        $related = Product::where('status', 'Success')
            ->where('id_category', $product->id_category)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => (int) $p->price,
                'image_url' => product_image_url($p->img ?: $p->product_image1),
            ]);

        $shareUrl = route('shop.catalog.show', $product->slug);

        // Description courte réutilisée par l'aperçu des réseaux sociaux et par
        // le message de partage. WhatsApp tronque au-delà de ~160 caractères.
        $shortDescription = str($product->description ?: '')
            ->stripTags()
            ->squish()
            ->limit(160)
            ->toString();

        if ($shortDescription === '') {
            $shortDescription = 'Commandez ' . $product->name . ' sur Poulet AFC, livré chez vous.';
        }

        return Inertia::render('Shop/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => (int) $product->price,
                'description' => $product->description,
                'stock' => $product->stock_init !== null ? (int) $product->stock_init : null,
                'category' => $product->category?->name,
                'options' => collect([$product->parameter1, $product->parameter2])->filter()->values(),
                'gallery' => $gallery,
                'share_url' => $shareUrl,
                'share_description' => $shortDescription,
            ],
            'related' => $related,
        ])->withViewData([
            // Rendu côté serveur dans resources/views/partials/social-meta.blade.php :
            // c'est la seule version que les robots d'aperçu (WhatsApp, Facebook…)
            // savent lire, puisqu'ils n'exécutent pas le JavaScript d'Inertia.
            'meta' => [
                'title' => $product->name,
                'description' => $shortDescription,
                'image' => route('shop.share.image.product', $product->slug),
                'image_width' => ImageDePartage::LARGEUR,
                'image_height' => ImageDePartage::HAUTEUR,
                'url' => $shareUrl,
                'type' => 'product',
                'price' => (int) $product->price,
            ],
        ]);
    }
}
