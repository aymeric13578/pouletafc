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

        $source = $produit->img ?: $produit->product_image1;

        return $this->servir(
            $this->images->fabriquer($source, 'produit-' . $produit->id),
            $this->images->reposeSurLeLogo($source)
        );
    }

    public function categorie(string $slug): BinaryFileResponse
    {
        $categorie = Category::where('slug', $slug)->firstOrFail();

        return $this->servir(
            $this->images->fabriquer($categorie->image, 'categorie-' . $categorie->id),
            $this->images->reposeSurLeLogo($categorie->image)
        );
    }

    private function servir(string $chemin, bool $repli): BinaryFileResponse
    {
        return response()->file($chemin, [
            /*
            | Une semaine pour une vraie photo : l'adresse ne change pas quand la
            | photo change, c'est le fichier derrière qui est refabriqué.
            |
            | Cinq minutes quand on sert le logo faute d'avoir retrouvé la photo.
            | C'est arrivé pendant une mise en production, le lien vers le dossier
            | des images étant recréé en fin de déploiement : un robot d'aperçu
            | passé à cet instant aurait retenu le logo pour des jours. Une durée
            | courte le fait revenir, et il trouvera la photo.
            */
            'Cache-Control' => $repli ? 'public, max-age=300' : 'public, max-age=604800',
        ]);
    }
}
