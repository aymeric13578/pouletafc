<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le panier est composé sur le téléphone et validé d'un seul envoi.
 *
 * Ce qui est éprouvé ici : le panier, ses articles et la commande sont écrits
 * ensemble ; une tentative rejouée ne recommande pas ; et le prix retenu est
 * celui de la base, jamais celui transmis par l'appareil.
 *
 * Ces cas créent leurs propres lignes et les effacent : la configuration de test
 * pointe sur la base de développement, qu'un rafraîchissement viderait.
 */
class ValidationPanierTest extends TestCase
{
    private ?User $client = null;

    private array $paniers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::first();

        if (! $this->client) {
            $this->markTestSkipped('Aucun client en base pour éprouver la validation.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->paniers) {
            DB::table('order_details')->whereIn('id_cart', $this->paniers)->delete();
            DB::table('cart_items')->whereIn('cart_id', $this->paniers)->delete();
            DB::table('carts')->whereIn('id', $this->paniers)->delete();
        }

        parent::tearDown();
    }

    private function unProduit(): Product
    {
        $produit = Product::where('status', 'Success')->first();

        if (! $produit) {
            $this->markTestSkipped('Aucun produit en vente.');
        }

        return $produit;
    }

    private function valider(array $corps)
    {
        $reponse = $this->postJson('/api/v1.0/validerPanier', $corps);

        if ($id = $reponse->json('data.id_cart')) {
            $this->paniers[] = $id;
        }

        return $reponse;
    }

    public function test_un_panier_devient_une_commande_en_un_envoi(): void
    {
        $produit = $this->unProduit();

        $reponse = $this->valider([
            'user_id' => $this->client->id,
            'cle_unique' => 'essai-' . uniqid(),
            'delivery_address' => 'Akwa',
            'delivery_fees' => 500,
            'articles' => [['product_id' => $produit->id, 'quantity' => 2]],
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('response', 200);
        $reponse->assertJsonPath('doublon', false);

        $idPanier = $reponse->json('data.id_cart');
        $this->assertNotNull($idPanier, 'La commande doit porter le panier créé avec elle.');

        // Le panier et ses articles existent, écrits dans la même transaction.
        $this->assertSame(1, DB::table('cart_items')->where('cart_id', $idPanier)->count());

        // Le montant vient du catalogue, pas de l'appareil.
        $attendu = (int) ($produit->price * 2 + 500);
        $this->assertSame($attendu, (int) $reponse->json('data.price'));
    }

    public function test_le_prix_transmis_par_le_telephone_est_ignore(): void
    {
        $produit = $this->unProduit();

        $reponse = $this->valider([
            'user_id' => $this->client->id,
            'cle_unique' => 'essai-' . uniqid(),
            'delivery_address' => 'Akwa',
            'delivery_fees' => 0,
            // Un panier tenu sur l'appareil ne doit pas pouvoir dicter le montant.
            'articles' => [['product_id' => $produit->id, 'quantity' => 1, 'amount' => 1]],
        ]);

        $this->assertSame((int) $produit->price, (int) $reponse->json('data.price'));
    }

    public function test_rejouer_la_meme_cle_ne_commande_pas_deux_fois(): void
    {
        $produit = $this->unProduit();
        $cle = 'essai-rejoue-' . uniqid();

        $corps = [
            'user_id' => $this->client->id,
            'cle_unique' => $cle,
            'delivery_address' => 'Akwa',
            'delivery_fees' => 0,
            'articles' => [['product_id' => $produit->id, 'quantity' => 1]],
        ];

        $premiere = $this->valider($corps);
        $seconde = $this->valider($corps);

        $seconde->assertJsonPath('doublon', true);
        $this->assertSame($premiere->json('data.id'), $seconde->json('data.id'));
    }

    public function test_un_panier_vide_est_refuse(): void
    {
        $this->postJson('/api/v1.0/validerPanier', [
            'user_id' => $this->client->id,
            'articles' => [],
        ])->assertJsonPath('response', 400);
    }

    public function test_un_produit_disparu_du_catalogue_ne_bloque_pas_la_commande(): void
    {
        $produit = $this->unProduit();

        $reponse = $this->valider([
            'user_id' => $this->client->id,
            'cle_unique' => 'essai-' . uniqid(),
            'delivery_address' => 'Akwa',
            'delivery_fees' => 0,
            'articles' => [
                ['product_id' => $produit->id, 'quantity' => 1],
                ['product_id' => 999999999, 'quantity' => 3],
            ],
        ]);

        $reponse->assertJsonPath('response', 200);
        $this->assertSame((int) $produit->price, (int) $reponse->json('data.price'));
    }
}
