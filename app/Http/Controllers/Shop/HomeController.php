<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Services\MobileAppService;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(MobileAppService $mobileApp): Response
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

        /*
        | Huit produits sur la vingtaine du catalogue : l'accueil n'en montrait
        | qu'un tiers, et les plus récents seulement. Vingt-quatre couvre le
        | catalogue actuel tout en gardant une borne, pour que la page ne
        | s'allonge pas sans fin le jour où il grossira — « Voir tout » mène de
        | toute façon au catalogue complet.
        */
        $products = Product::where('status', 'Success')
            ->latest('id')
            ->take(24)
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
            'mobileApp' => $mobileApp->toArray(),
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
