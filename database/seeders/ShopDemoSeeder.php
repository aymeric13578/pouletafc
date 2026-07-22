<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de données minimal pour tester le parcours e-commerce (boutique,
 * panier, checkout, espace client) en local, sans dépendre du seeder
 * marketplace existant (Agents/Marchands/Shops).
 */
class ShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Poulets entiers', 'image' => 'images/categories/poulets-entiers.jpg', 'products' => [
                ['name' => 'Poulet fermier entier 1.5kg', 'price' => 4500, 'image' => 'images/products/poulet-entier-1.jpg'],
                ['name' => 'Poulet fermier entier 2kg', 'price' => 5800, 'image' => 'images/products/poulet-entier-2.jpg'],
                ['name' => 'Poulet bio label rouge', 'price' => 7200, 'image' => 'images/products/poulet-bio.jpg'],
            ]],
            ['name' => 'Découpes de poulet', 'image' => 'images/categories/decoupes-poulet.jpg', 'products' => [
                ['name' => 'Cuisses de poulet (x4)', 'price' => 3200, 'image' => 'images/products/cuisses-poulet.jpg'],
                ['name' => 'Filets de poulet (1kg)', 'price' => 4100, 'image' => 'images/products/filets-poulet.jpg'],
                ['name' => 'Ailes de poulet (1kg)', 'price' => 2600, 'image' => 'images/products/ailes-poulet.jpg'],
            ]],
            ['name' => 'Œufs & produits frais', 'image' => 'images/categories/oeufs-frais.jpg', 'products' => [
                ['name' => 'Plateau de 30 œufs frais', 'price' => 2800, 'image' => 'images/products/oeufs.jpg'],
                ['name' => 'Beurre fermier 250g', 'price' => 1800, 'image' => 'images/products/beurre.jpg'],
            ]],
            ['name' => 'Marinades & accompagnements', 'image' => 'images/categories/marinades.jpg', 'products' => [
                ['name' => 'Marinade épicée maison', 'price' => 1200, 'image' => 'images/products/marinade.jpg'],
                ['name' => 'Frites surgelées 1kg', 'price' => 1500, 'image' => 'images/products/frites.jpg'],
            ]],
        ];

        foreach ($categories as $data) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'ref' => 'CAT-'.strtoupper(Str::random(6)),
                    'image' => $data['image'],
                    'status' => 'Success',
                ]
            );

            foreach ($data['products'] as $productData) {
                Product::updateOrCreate(
                    ['slug' => Str::slug($productData['name'])],
                    [
                        'name' => $productData['name'],
                        'price' => $productData['price'],
                        'stock_init' => rand(5, 40),
                        'status' => 'Success',
                        'description' => "Produit frais de qualité, sélectionné par Poulet AFC.\nLivré réfrigéré, à consommer rapidement après réception.",
                        'ref' => 'PROD-'.strtoupper(Str::random(6)),
                        'id_category' => $category->id,
                        'img' => $productData['image'],
                    ]
                );
            }
        }

        Article::updateOrCreate(
            ['title' => 'Promo de la semaine : -10% sur les poulets entiers'],
            [
                'image' => 'images/articles/promo-poulet.jpg',
                'page' => 'blog',
                'status' => 'Success',
                'description' => "Profitez de -10% sur tous les poulets entiers jusqu'à dimanche.\nOffre valable en ligne uniquement.",
            ]
        );

        Article::updateOrCreate(
            ['title' => 'Notre recette du dimanche : poulet rôti aux épices'],
            [
                'image' => 'images/articles/recette-poulet-roti.jpg',
                'page' => 'blog',
                'status' => 'Success',
                'description' => "Une recette simple et savoureuse pour régaler toute la famille le dimanche.",
            ]
        );

        User::firstOrCreate(
            ['email' => 'client@pouletafc.test'],
            [
                'name' => 'Client',
                'last_name' => 'Demo',
                'password' => Hash::make('password'),
                'phone' => '690000000',
                'role' => 'user',
                'status' => 'Success',
                'sexe' => 'H',
            ]
        );
    }
}
