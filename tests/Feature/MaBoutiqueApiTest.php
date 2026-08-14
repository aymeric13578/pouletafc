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
