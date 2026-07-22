<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $categories = Category::orderBy('name')
            ->take(6)
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image_url' => product_image_url($category->image),
            ]);

        $products = Product::where('status', 'Success')
            ->latest('id')
            ->take(8)
            ->get()
            ->map(fn (Product $product) => $this->transformProduct($product));

        $articles = Article::where('status', 'Success')
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'image_url' => product_image_url($article->image),
                'excerpt' => str($article->description)->stripTags()->limit(120)->toString(),
            ]);

        return Inertia::render('Home', [
            'categories' => $categories,
            'products' => $products,
            'articles' => $articles,
        ]);
    }

    protected function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => (int) $product->price,
            'image_url' => product_image_url($product->img ?: $product->product_image1),
        ];
    }
}
