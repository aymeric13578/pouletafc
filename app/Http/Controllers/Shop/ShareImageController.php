<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageDePartage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert l'image d'aperçu d'un produit ou d'une catégorie.
 *
 * Passer par une adresse dédiée plutôt que de fabriquer l'image pendant le rendu
 * de la page : le visiteur n'attend jamais la compression, qui n'a lieu qu'au
 * premier passage du robot d'aperçu — et une seule fois, le résultat étant
 * ensuite servi depuis le cache.
 */
class ShareImageController extends Controller
{
    public function __construct(private readonly ImageDePartage $images)
    {
    }

    public function produit(string $slug): BinaryFileResponse
    {
        $produit = Product::where('status', 'Success')->where('slug', $slug)->firstOrFail();

        return $this->servir(
            $this->images->fabriquer($produit->img ?: $produit->product_image1, 'produit-' . $produit->id)
        );
    }

    public function categorie(string $slug): BinaryFileResponse
    {
        $categorie = Category::where('slug', $slug)->firstOrFail();

        return $this->servir(
            $this->images->fabriquer($categorie->image, 'categorie-' . $categorie->id)
        );
    }

    private function servir(string $chemin): BinaryFileResponse
    {
        return response()->file($chemin, [
            // Une semaine : l'adresse ne change pas quand la photo change, c'est
            // le fichier derrière qui est refabriqué. Les robots d'aperçu gardent
            // de toute façon leur propre cache, souvent plus long.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
