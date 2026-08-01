<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Vérifie les deux défauts qui rendaient invisible toute image ajoutée depuis le
 * tableau de bord : le fichier écrit hors du dossier servi, et l'URL préfixée par
 * un domaine mort.
 */
class DashboardProductImageTest extends TestCase
{
    public function test_le_disque_uploads_pointe_sur_le_dossier_reellement_servi(): void
    {
        $this->assertSame(
            realpath(public_path('upload')),
            realpath(config('filesystems.disks.uploads.root')),
            "Le disque 'uploads' doit écrire dans public/upload, seul dossier servi par le webserver."
        );
    }

    public function test_une_image_stockee_atterrit_dans_public_upload_et_non_a_la_racine(): void
    {
        $file = UploadedFile::fake()->image('poulet.jpg');
        $name = 'test-' . uniqid() . '.jpg';

        $file->storeAs('', $name, 'uploads');

        $this->assertFileExists(
            public_path('upload/' . $name),
            'Le fichier doit être écrit dans public/upload.'
        );
        $this->assertFileDoesNotExist(
            base_path('upload/' . $name),
            "Le fichier ne doit pas atterrir à la racine du projet (ancien comportement du disque 'public')."
        );

        @unlink(public_path('upload/' . $name));
    }

    public function test_l_url_generee_ne_reference_plus_l_ancien_domaine(): void
    {
        $url = asset('upload/exemple.jpg');

        $this->assertStringNotContainsString('2gether-network.com', $url);
        $this->assertStringContainsString('/upload/exemple.jpg', $url);
    }

    public function test_la_page_produits_s_affiche_avec_l_indicateur_de_chargement(): void
    {
        $user = \App\Models\User::first();

        if (! $user) {
            $this->markTestSkipped('Aucun utilisateur en base pour authentifier la requête.');
        }

        $response = $this->actingAs($user)->get('/dashboard/products');

        $response->assertOk();
        $response->assertSee('product_image1', false);
        $response->assertSee('livewire-upload-progress', false);
        $response->assertSee('wire:loading.attr', false);
        $response->assertDontSee('2gether-network', false);
    }

    public function test_le_composant_ne_contient_plus_de_domaine_en_dur(): void
    {
        $source = file_get_contents(resource_path('views/pages/dashboard/products.blade.php'));

        $this->assertStringNotContainsString(
            'pouletafc.2gether-network.com',
            $source,
            'Aucune URL de domaine ne doit être écrite en dur dans le composant.'
        );
        $this->assertStringNotContainsString(
            "storeAs('upload', \$img_name1, 'public')",
            $source,
            "Le disque 'public' ne doit plus être utilisé pour les images produits."
        );
    }
}
