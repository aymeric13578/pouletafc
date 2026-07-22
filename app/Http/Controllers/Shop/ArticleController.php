<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(): Response
    {
        $articles = Article::where('status', 'Success')->latest('id')->paginate(9);

        $articles->getCollection()->transform(fn (Article $article) => [
            'id' => $article->id,
            'title' => $article->title,
            'image_url' => product_image_url($article->image),
            'excerpt' => str($article->description)->stripTags()->limit(140)->toString(),
        ]);

        return Inertia::render('Blog/Index', [
            'articles' => $articles,
        ]);
    }

    public function show(Article $article): Response
    {
        $recent = Article::where('status', 'Success')
            ->where('id', '!=', $article->id)
            ->latest('id')
            ->take(3)
            ->get()
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'image_url' => product_image_url($a->image),
            ]);

        return Inertia::render('Blog/Show', [
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'image_url' => product_image_url($article->image),
                'description' => $article->description,
                'date' => optional($article->created_at)->translatedFormat('d F Y'),
            ],
            'recent' => $recent,
        ]);
    }
}
