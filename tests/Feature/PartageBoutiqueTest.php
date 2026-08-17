<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\ImageDePartage;
use Tests\TestCase;

/**
 * Le partage d'un produit ou d'une catégorie, vu comme le voit le robot d'aperçu
 * de WhatsApp : il lit le HTML initial, sans exécuter JavaScript, puis va
 * chercher l'image annoncée.
 *
 * Ces cas lisent la base sans jamais l'écrire — la configuration de test pointe
 * sur la base de développement, qu'un rafraîchissement viderait.
 */
class PartageBoutiqueTest extends TestCase
{
    private function unProduit(): Product
    {
        $produit = Product::where('status', 'Success')->whereNotNull('slug')->where('slug', '!=', '')->first();

        if (! $produit) {
            $this->markTestSkipped('Aucun produit en base pour éprouver le partage.');
        }

        return $produit;
    }

    private function uneCategorie(): Category
    {
        $categorie = Category::whereNotNull('slug')->where('slug', '!=', '')->first();

        if (! $categorie) {
            $this->markTestSkipped('Aucune catégorie en base pour éprouver le partage.');
        }

        return $categorie;
    }

    public function test_la_fiche_produit_annonce_son_apercu_dans_le_html_initial(): void
    {
        $produit = $this->unProduit();

        $reponse = $this->get(route('shop.catalog.show', $produit->slug));

        $reponse->assertOk();
        // Le robot n'exécute pas JavaScript : ces balises doivent être servies
        // telles quelles, pas ajoutées par React après coup.
        $reponse->assertSee('property="og:image"', false);
        $reponse->assertSee(route('shop.share.image.product', $produit->slug), false);
        $reponse->assertSee('content="' . ImageDePartage::LARGEUR . '"', false);
    }

    public function test_une_categorie_a_son_adresse_partageable(): void
    {
        $categorie = $this->uneCategorie();

        $reponse = $this->get(route('shop.catalog.category', $categorie->slug));

        $reponse->assertOk();
        $reponse->assertSee(route('shop.share.image.category', $categorie->slug), false);
        $reponse->assertSee('property="og:url"', false);
        $reponse->assertSee(route('shop.catalog.category', $categorie->slug), false);
    }

    public function test_l_apercu_servi_est_une_image_compressee(): void
    {
        $produit = $this->unProduit();

        $reponse = $this->get(route('shop.share.image.product', $produit->slug));

        $reponse->assertOk();
        $reponse->assertHeader('content-type', 'image/jpeg');

        // La réponse renvoie un fichier du disque, pas un flux : on lit le
        // fichier qu'elle désigne.
        $octets = file_get_contents($reponse->baseResponse->getFile()->getPathname());

        $this->assertLessThanOrEqual(300_000, strlen($octets), 'Au-delà, WhatsApp renonce à afficher l\'aperçu.');

        [$largeur, $hauteur] = getimagesizefromstring($octets);
        $this->assertSame(ImageDePartage::LARGEUR, $largeur);
        $this->assertSame(ImageDePartage::HAUTEUR, $hauteur);
    }

    public function test_une_categorie_inconnue_ne_fabrique_pas_d_apercu(): void
    {
        $this->get(route('shop.share.image.category', 'categorie-qui-nexiste-pas'))->assertNotFound();
    }
}
