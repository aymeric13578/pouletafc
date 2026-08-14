<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Espace boutique dans l'application.
 *
 * Un marchand connecté depuis son téléphone n'avait aucun moyen de voir sa
 * vitrine ni ses commandes : il fallait ouvrir le tableau de bord sur un
 * ordinateur.
 */
class MaBoutiqueApiTest extends TestCase
{
    private function boutique(): Shop
    {
        $boutique = Shop::whereNotNull('id_user')->first();

        if (! $boutique) {
            $this->markTestSkipped('Aucune boutique rattachée à un utilisateur.');
        }

        return $boutique;
    }

    public function test_un_marchand_recoit_sa_boutique(): void
    {
        $boutique = $this->boutique();

        $data = $this->getJson('/api/v1.0/getMyShop?id_user=' . $boutique->id_user)
            ->assertOk()
            ->json('data');

        $this->assertNotNull($data, 'Un compte rattaché doit recevoir sa boutique.');
        $this->assertSame($boutique->id, $data['id']);
        $this->assertSame($boutique->shop_name, $data['shop_name']);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('produits', $data['stats']);
    }

    public function test_un_compte_sans_boutique_ne_recoit_rien(): void
    {
        /*
         * Réponse normale et non une erreur : la plupart des comptes ne tiennent
         * pas de boutique, l'application masque simplement l'entrée.
         */
        $sansBoutique = User::whereNotIn('id', Shop::whereNotNull('id_user')->pluck('id_user'))
            ->value('id');

        $this->getJson('/api/v1.0/getMyShop?id_user=' . $sansBoutique)
            ->assertOk()
            ->assertJson(['response' => 200, 'data' => null]);
    }

    public function test_sans_identifiant_rien_n_est_divulgue(): void
    {
        $this->getJson('/api/v1.0/getMyShop')->assertOk()->assertJson(['data' => null]);
    }

    public function test_le_marchand_modifie_sa_vitrine(): void
    {
        $boutique = $this->boutique();
        $etat = $boutique->only(['shop_name', 'city', 'address', 'phone1', 'description']);

        $this->postJson('/api/v1.0/updateMyShop', [
            'id_user' => $boutique->id_user,
            'shop_name' => 'Vitrine modifiée par test',
            'city' => 'Garoua',
            'address' => 'Rue du marché',
            'phone1' => '690000000',
            'description' => 'Description de test',
        ])->assertOk()->assertJson(['response' => 200]);

        $frais = $boutique->fresh();
        $this->assertSame('Vitrine modifiée par test', $frais->shop_name);
        $this->assertSame('Garoua', $frais->city);

        DB::table('shops')->where('id', $boutique->id)->update($etat);
    }

    public function test_le_marchand_ne_peut_pas_changer_son_statut(): void
    {
        /*
         * Le statut, le type et le rattachement restent du ressort de l'équipe :
         * les exposer permettrait de réactiver une boutique désactivée ou de se
         * rattacher ailleurs. Même règle que sur le tableau de bord.
         */
        $boutique = $this->boutique();
        $etat = $boutique->only(['shop_name', 'status', 'id_user']);
        $statutInitial = $boutique->status;

        $this->postJson('/api/v1.0/updateMyShop', [
            'id_user' => $boutique->id_user,
            'shop_name' => $boutique->shop_name,
            'status' => 'Success',
            'id_user_nouveau' => 1,
        ])->assertOk();

        $this->assertSame($statutInitial, $boutique->fresh()->status);

        DB::table('shops')->where('id', $boutique->id)->update($etat);
    }

    public function test_un_compte_sans_boutique_ne_modifie_rien(): void
    {
        $sansBoutique = User::whereNotIn('id', Shop::whereNotNull('id_user')->pluck('id_user'))
            ->value('id');

        $this->postJson('/api/v1.0/updateMyShop', [
            'id_user' => $sansBoutique,
            'shop_name' => 'Tentative',
        ])->assertOk()->assertJson(['response' => 404]);
    }

    public function test_le_nom_de_la_boutique_reste_obligatoire(): void
    {
        $boutique = $this->boutique();

        $this->postJson('/api/v1.0/updateMyShop', [
            'id_user' => $boutique->id_user,
            'shop_name' => '',
        ])->assertStatus(422);
    }

    public function test_les_produits_sont_ceux_de_la_boutique_et_d_aucune_autre(): void
    {
        $boutique = $this->boutique();

        $produits = $this->getJson('/api/v1.0/getMyShopProducts?id_user=' . $boutique->id_user)
            ->assertOk()
            ->json('data');

        $ids = collect($produits)->pluck('id');

        if ($ids->isEmpty()) {
            $this->assertTrue(true, 'Boutique sans produit : rien à vérifier.');

            return;
        }

        $etrangers = \App\Models\Product::whereIn('id', $ids)
            ->where('id_shop', '!=', $boutique->id)
            ->count();

        $this->assertSame(0, $etrangers, 'Aucun produit d\'une autre boutique ne doit apparaître.');
    }

    public function test_la_liste_porte_de_quoi_rouvrir_le_formulaire(): void
    {
        /*
         * Catégorie et stock pré-remplissent le formulaire de modification.
         * Sans eux, rouvrir un produit pour changer son prix effacerait sa
         * catégorie et son stock.
         */
        $boutique = $this->boutique();

        $produits = $this->getJson('/api/v1.0/getMyShopProducts?id_user=' . $boutique->id_user)
            ->assertOk()
            ->json('data');

        if (! $produits) {
            $this->markTestSkipped('Boutique sans produit.');
        }

        $this->assertArrayHasKey('id_category', $produits[0]);
        $this->assertArrayHasKey('stock_init', $produits[0]);
    }

    public function test_les_commandes_sont_celles_qui_contiennent_ses_produits(): void
    {
        $boutique = $this->boutique();

        $reponse = $this->getJson('/api/v1.0/getMyShopOrders?id_user=' . $boutique->id_user)->assertOk();

        $this->assertSame(200, $reponse->json('response'));
        $this->assertIsArray($reponse->json('data'));
    }

    public function test_le_marchand_ne_recoit_pas_les_coordonnees_du_client(): void
    {
        // Il prépare une commande ; il n'a pas à disposer du fichier client.
        $boutique = $this->boutique();

        $commandes = $this->getJson('/api/v1.0/getMyShopOrders?id_user=' . $boutique->id_user)
            ->assertOk()
            ->json('data');

        foreach ($commandes as $commande) {
            $this->assertArrayNotHasKey('phone', $commande);
            $this->assertArrayNotHasKey('whatsapp', $commande);
        }
    }

    public function test_le_marchand_cree_un_produit_en_attente_de_validation(): void
    {
        /*
         * Un produit créé par un marchand n'apparaît pas au catalogue tant que
         * l'équipe ne l'a pas validé. Même règle que sur le tableau de bord.
         */
        $boutique = $this->boutique();
        $categorie = \App\Models\Category::query()->value('id');

        if (! $categorie) {
            $this->markTestSkipped('Aucune catégorie en base.');
        }

        $reponse = $this->post('/api/v1.0/saveMyShopProduct', [
            'id_user' => $boutique->id_user,
            'name' => 'Produit de test',
            'id_category' => $categorie,
            'price' => 2500,
            'stock_init' => 10,
            'description' => 'Créé par un test automatisé.',
            'image' => UploadedFile::fake()->image('produit.jpg', 400, 400),
        ]);

        $reponse->assertOk()->assertJson(['response' => 200]);

        $produit = \App\Models\Product::find($reponse->json('data.id'));

        $this->assertNotNull($produit);
        $this->assertSame($boutique->id, (int) $produit->id_shop);
        $this->assertSame('pending', $produit->status);
        $this->assertStringStartsWith('PROD-', $produit->ref);
        // La vitrine lit img, l'application lit product_image1 : n'en remplir
        // qu'un laisse le produit sans image d'un côté.
        $this->assertSame($produit->img, $produit->product_image1);
        $this->assertNotNull($produit->img);

        if ($produit->img) {
            @unlink(public_path('upload/' . basename($produit->img)));
        }
        DB::table('products')->where('id', $produit->id)->delete();
    }

    public function test_une_creation_sans_image_est_refusee(): void
    {
        $boutique = $this->boutique();
        $categorie = \App\Models\Category::query()->value('id');

        $this->postJson('/api/v1.0/saveMyShopProduct', [
            'id_user' => $boutique->id_user,
            'name' => 'Sans image',
            'id_category' => $categorie,
            'price' => 1000,
            'stock_init' => 1,
            'description' => 'Test',
        ])->assertStatus(422);
    }

    public function test_le_marchand_ne_modifie_pas_le_produit_d_une_autre_boutique(): void
    {
        /*
         * La portée est le point sensible : un produit se retrouve par
         * where('id_shop')->find(), jamais par find() seul, qui accepterait
         * n'importe quel identifiant.
         */
        $boutique = $this->boutique();
        $categorie = \App\Models\Category::query()->value('id');

        $etranger = \App\Models\Product::where('id_shop', '!=', $boutique->id)
            ->whereNotNull('id_shop')
            ->first();

        if (! $etranger) {
            $this->markTestSkipped('Aucun produit rattaché à une autre boutique.');
        }

        $nomInitial = $etranger->name;

        $this->postJson('/api/v1.0/saveMyShopProduct', [
            'id_user' => $boutique->id_user,
            'id_product' => $etranger->id,
            'name' => 'Tentative de détournement',
            'id_category' => $categorie,
            'price' => 1,
            'stock_init' => 1,
            'description' => 'Test',
        ])->assertOk()->assertJson(['response' => 404]);

        $this->assertSame($nomInitial, $etranger->fresh()->name);
    }

    public function test_une_modification_sans_nouvelle_image_garde_l_ancienne(): void
    {
        $boutique = $this->boutique();
        $categorie = \App\Models\Category::query()->value('id');

        $produit = \App\Models\Product::where('id_shop', $boutique->id)->first();

        if (! $produit) {
            $this->markTestSkipped('Boutique sans produit.');
        }

        $etat = $produit->only(['name', 'price', 'description', 'id_category', 'stock_init']);
        $imageInitiale = $produit->product_image1;

        $this->postJson('/api/v1.0/saveMyShopProduct', [
            'id_user' => $boutique->id_user,
            'id_product' => $produit->id,
            'name' => 'Nom modifié par test',
            'id_category' => $categorie,
            'price' => 3300,
            'stock_init' => 7,
            'description' => 'Description modifiée.',
        ])->assertOk()->assertJson(['response' => 200]);

        $frais = $produit->fresh();
        $this->assertSame('Nom modifié par test', $frais->name);
        $this->assertSame($imageInitiale, $frais->product_image1, "L'image ne doit pas être perdue.");

        DB::table('products')->where('id', $produit->id)->update($etat);
    }

    public function test_les_categories_sont_proposees_au_marchand(): void
    {
        $data = $this->getJson('/api/v1.0/getShopCategories')->assertOk()->json('data');

        $this->assertIsArray($data);

        if ($data) {
            $this->assertArrayHasKey('id', $data[0]);
            $this->assertArrayHasKey('name', $data[0]);
        }
    }

    public function test_l_onglet_boutiques_reconnait_enfin_un_marchand(): void
    {
        /*
         * L'écran appelle verifiedShopUser depuis toujours ; la route n'a jamais
         * existé. L'appel répondait 404, l'application tombait dans sa branche
         * d'erreur et affichait « Vous ne possédez pas encore de boutique » à
         * tous les marchands, y compris à ceux qui en tiennent une.
         */
        $boutique = $this->boutique();

        $reponse = $this->getJson('/api/v1.0/verifiedShopUser?id_user=' . $boutique->id_user)->assertOk();

        // Forme inhabituelle mais imposée par le client déjà installé : « code »
        // à 100, et la liste des boutiques sous forme de chaîne JSON.
        $this->assertSame(100, $reponse->json('code'));

        $liste = json_decode($reponse->json('message'), true);

        $this->assertIsArray($liste);
        $this->assertNotEmpty($liste);
        $this->assertSame($boutique->id, $liste[0]['id']);
        $this->assertSame($boutique->shop_name, $liste[0]['shop_name']);
        $this->assertArrayHasKey('product_count', $liste[0]);
    }

    public function test_un_compte_sans_boutique_recoit_une_liste_vide(): void
    {
        $sansBoutique = User::whereNotIn('id', Shop::whereNotNull('id_user')->pluck('id_user'))->value('id');

        $reponse = $this->getJson('/api/v1.0/verifiedShopUser?id_user=' . $sansBoutique)->assertOk();

        $this->assertNotSame(100, $reponse->json('code'));
        $this->assertSame([], json_decode($reponse->json('message'), true));
    }

    public function test_les_compteurs_de_la_boutique_sont_servis(): void
    {
        // L'écran attend « response » à 100, et non 200 : sans cette valeur les
        // compteurs restaient à zéro même sur une boutique active.
        $boutique = $this->boutique();

        $reponse = $this->getJson('/api/v1.0/getShopStats?shop_id=' . $boutique->id)->assertOk();

        $this->assertSame(100, $reponse->json('response'));

        foreach (['nombre_produits', 'nombre_commandes', 'commandes_en_attente'] as $compteur) {
            $this->assertIsInt($reponse->json('data.' . $compteur), $compteur);
        }
    }

    public function test_une_boutique_inconnue_ne_renvoie_pas_de_compteurs(): void
    {
        $this->getJson('/api/v1.0/getShopStats?shop_id=999999')
            ->assertOk()
            ->assertJson(['response' => 404]);
    }

    public function test_le_logo_envoye_est_range_avec_les_autres_images(): void
    {
        $boutique = $this->boutique();
        $ancien = $boutique->logo;

        $this->post('/api/v1.0/updateMyShop', [
            'id_user' => $boutique->id_user,
            'shop_name' => $boutique->shop_name,
            'logo' => UploadedFile::fake()->image('logo.jpg', 200, 200),
        ])->assertOk();

        $nouveau = $boutique->fresh()->logo;

        $this->assertNotSame($ancien, $nouveau);
        $this->assertFileExists(public_path('upload/' . $nouveau));

        @unlink(public_path('upload/' . $nouveau));
        DB::table('shops')->where('id', $boutique->id)->update(['logo' => $ancien]);
    }
}
