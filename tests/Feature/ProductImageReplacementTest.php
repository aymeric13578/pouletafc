<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Remplacer l'image d'un produit depuis le tableau de bord ajoutait la nouvelle
 * sans retirer l'ancienne : le formulaire n'écrivait que product_image1, alors
 * que la boutique affiche "img" en principale et construit sa galerie avec les
 * quatre colonnes.
 *
 * Le composant vit dans une page Folio enveloppée par son layout : Livewire
 * refuse de le monter isolément (plusieurs éléments racines). Ces tests
 * vérifient donc le formulaire rendu et le code, pas un aller-retour complet.
 * Le remplacement lui-même reste à confirmer par un essai manuel.
 */
class ProductImageReplacementTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_le_formulaire_distingue_image_principale_et_secondaires(): void
    {
        $response = $this->actingAs($this->admin())->get('/dashboard/products');

        $response->assertOk();
        $response->assertSee('main_image', false);
        $response->assertSee('secondary_image1', false);
        $response->assertSee('secondary_image2', false);
        $response->assertSee('Image principale');
        $response->assertSee('Images secondaires');
    }

    public function test_l_image_principale_alimente_bien_la_colonne_img(): void
    {
        $source = file_get_contents(resource_path('views/pages/dashboard/products.blade.php'));

        $this->assertStringContainsString("\$data['img'] = \$url;", $source,
            "L'image principale doit écrire dans 'img' : c'est la colonne que la boutique affiche.");
        $this->assertStringContainsString("\$data['product_image1'] = \$url;", $source,
            "'product_image1' doit pointer sur le même fichier pour l'application mobile.");
        $this->assertStringContainsString("\$data['product_image2']", $source);
        $this->assertStringContainsString("\$data['product_image3']", $source);
    }

    public function test_les_fichiers_remplaces_sont_supprimes_du_disque(): void
    {
        $source = file_get_contents(resource_path('views/pages/dashboard/products.blade.php'));

        $this->assertStringContainsString('deleteStoredImage', $source,
            'Les fichiers remplacés doivent être supprimés, sinon public/upload enfle à chaque modification.');
    }

    public function test_un_client_ne_peut_pas_ouvrir_le_tableau_de_bord(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/dashboard')->assertForbidden();
        $this->actingAs($client)->get('/dashboard/products')->assertForbidden();
    }

    public function test_un_administrateur_y_accede(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertOk();
    }
}
