<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPanierTest extends TestCase
{
    private array $utilisateursCrees = [];
    private ?Cart $panier = null;
    private ?CartItem $article = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('carts', 'user_id') || ! Schema::hasColumn('cart_items', 'user_id')) {
            $this->markTestSkipped('Colonne user_id absente de carts/cart_items sur cette base locale.');
        }
    }

    protected function tearDown(): void
    {
        $this->article?->delete();
        $this->panier?->delete();
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerClient(): User
    {
        $client = User::factory()->create(['role' => 'user', 'status' => 'Success']);
        $this->utilisateursCrees[] = $client;

        return $client;
    }

    public function test_deleteCart_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/DeleteCart?id=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_deleteCart_avec_le_jeton_d_un_autre_client_c_est_403(): void
    {
        $proprietaire = $this->creerClient();
        $intrus = $this->creerClient();

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);

        $jeton = $intrus->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/DeleteCart?token=' . $jeton . '&id=' . $this->panier->id)
            ->assertOk()->assertJsonPath('response', 403);

        $this->panier->refresh();
        $this->assertSame('pending', $this->panier->status);
    }

    public function test_deleteCart_avec_le_bon_client_fonctionne(): void
    {
        $proprietaire = $this->creerClient();

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);

        $jeton = $proprietaire->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/DeleteCart?token=' . $jeton . '&id=' . $this->panier->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->panier->refresh();
        $this->assertSame('failed', $this->panier->status);
    }

    public function test_updateItem_avec_le_jeton_d_un_autre_client_c_est_403(): void
    {
        $proprietaire = $this->creerClient();
        $intrus = $this->creerClient();
        $produit = Product::first();
        if (! $produit) {
            $this->markTestSkipped('Aucun produit en base pour ce test.');
        }

        $this->panier = Cart::create(['user_id' => $proprietaire->id, 'status' => 'pending']);
        $this->article = CartItem::create([
            'user_id' => $proprietaire->id,
            'product_id' => $produit->id,
            'cart_id' => $this->panier->id,
            'quantity' => 1,
            'amount' => $produit->price,
            'status' => 'Success',
        ]);

        $jeton = $intrus->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/updateItem?token=' . $jeton . '&id=' . $this->article->id . '&quantity=5')
            ->assertOk()->assertJsonPath('response', 403);

        $this->article->refresh();
        $this->assertSame(1, (int) $this->article->quantity);
    }
}
