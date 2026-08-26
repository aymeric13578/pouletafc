<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ArticleController::storeArticle préfixait chaque image avec l'ancien
 * domaine "https://pouletafc.2gether-network.com/", qui ne répond plus
 * (déjà relevé pour d'autres images dans dashboard/products.blade.php).
 * Toute bannière ("article") créée avant le correctif du contrôleur reste
 * invisible côté client tant que son URL n'est pas réécrite ici — il n'existe
 * aucun écran d'édition pour la corriger à la main.
 */
return new class extends Migration
{
    private const ANCIEN_PREFIXE = 'https://pouletafc.2gether-network.com/';

    public function up(): void
    {
        if (! Schema::hasTable('articles')) {
            return;
        }

        DB::table('articles')
            ->where('image', 'like', self::ANCIEN_PREFIXE . '%')
            ->get(['id', 'image'])
            ->each(function ($article) {
                $chemin = substr($article->image, strlen(self::ANCIEN_PREFIXE));
                DB::table('articles')
                    ->where('id', $article->id)
                    ->update(['image' => url($chemin)]);
            });
    }

    public function down(): void
    {
        // Irréversible par choix : revenir à un domaine mort ne rendrait
        // aucune donnée, seulement une régression.
    }
};
