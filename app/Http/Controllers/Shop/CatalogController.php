<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::where('status', 'Success');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->integer('category')) {
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

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['q', 'category', 'min_price', 'max_price', 'sort']),
        ]);
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
            ],
            'related' => $related,
        ]);
    }
}
