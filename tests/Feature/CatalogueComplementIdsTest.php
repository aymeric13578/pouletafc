<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

/**
 * `complement_ids` sur le catalogue — additif, pour que plouletafcapp puisse
 * savoir hors-ligne quels compléments accompagnent un produit, sans appel
 * réseau dédié au moment de valider le panier (voir ComplementsCatalogCache
 * côté app, et App\Support\ComplementsProposes côté serveur pour la règle
 * déjà existante).
 */
class CatalogueComplementIdsTest extends TestCase
{
    private array $produits = [];

    private function produit(string $nom, array $extra = []): Product
    {
        $produit = Product::create(array_merge([
            'name' => $nom, 'price' => 1000, 'stock_init' => 10,
            'status' => 'Success', 'description' => $nom,
        ], $extra));

        $this->produits[] = $produit->id;

        return $produit;
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\DB::table('product_complement')
            ->whereIn('product_id', $this->produits)
            ->orWhereIn('complement_id', $this->produits)
            ->delete();

        Product::whereIn('id', $this->produits)->forceDelete();

        parent::tearDown();
    }

    public function test_getAllProducts_expose_les_ids_de_complements(): void
    {
        $poulet = $this->produit('Poulet complement_ids');
        $frites = $this->produit('Frites complement_ids', ['is_complement' => true]);
        $poulet->complements()->attach($frites->id);

        $ligne = collect(
            $this->getJson('/api/v1.0/getAllProducts')->assertOk()->json('data')
        )->firstWhere('id', $poulet->id);

        $this->assertNotNull($ligne, 'Le produit doit apparaître dans le catalogue.');
        $this->assertSame([$frites->id], $ligne['complement_ids']);
    }

    public function test_getAllProducts_renvoie_un_tableau_vide_sans_complement(): void
    {
        $nu = $this->produit('Boisson complement_ids');

        $ligne = collect(
            $this->getJson('/api/v1.0/getAllProducts')->assertOk()->json('data')
        )->firstWhere('id', $nu->id);

        $this->assertSame([], $ligne['complement_ids']);
    }

    public function test_getProductsByCategory_expose_aussi_les_ids_de_complements(): void
    {
        $categorie = Category::create(['name' => 'Catégorie complement_ids test']);

        $poulet = $this->produit('Poulet categorie complement_ids', ['id_category' => $categorie->id]);
        $frites = $this->produit('Frites categorie complement_ids', ['is_complement' => true]);
        $poulet->complements()->attach($frites->id);

        $ligne = collect(
            $this->getJson('/api/v1.0/getProductsByCategory?id=' . $categorie->id)
                ->assertOk()->json('data')
        )->firstWhere('id', $poulet->id);

        $this->assertNotNull($ligne);
        $this->assertSame([$frites->id], $ligne['complement_ids']);

        $categorie->delete();
    }
}
