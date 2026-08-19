<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L'ancien chemin de commande doit continuer de fonctionner.
 *
 * La plupart des téléphones tournent encore sur la version qui constitue le
 * panier au fur et à mesure puis appelle « createOrder ». Le nouveau chemin ne
 * doit rien leur enlever.
 */
class AncienCheminDeCommandeTest extends TestCase
{
    private array $paniers = [];

    protected function tearDown(): void
    {
        if ($this->paniers) {
            DB::table('order_details')->whereIn('id_cart', $this->paniers)->delete();
            DB::table('cart_items')->whereIn('cart_id', $this->paniers)->delete();
            DB::table('carts')->whereIn('id', $this->paniers)->delete();
        }

        parent::tearDown();
    }

    public function test_l_ancien_createOrder_cree_toujours_la_commande(): void
    {
        $client = User::first();
        $produit = Product::where('status', 'Success')->first();

        if (! $client || ! $produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'pending', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        CartItem::create([
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'product_id' => $produit->id,
            'quantity' => 1,
            'amount' => $produit->price,
            'status' => 'Success',
        ]);

        $reponse = $this->postJson('/api/v1.0/createOrder', [
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'delivery_address' => 'Akwa',
            'delivery_fees' => 500,
        ]);

        $reponse->assertOk();
        $reponse->assertJsonPath('response', 200);
        $this->assertSame((int) ($produit->price + 500), (int) $reponse->json('data.price'));

        // Le panier n'est fermé qu'une fois la commande enregistrée.
        $this->assertSame('Success', Cart::find($panier->id)->status);
    }

    public function test_le_meme_panier_rappele_ne_recommande_pas(): void
    {
        $client = User::first();
        $produit = Product::where('status', 'Success')->first();

        if (! $client || ! $produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'pending', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        CartItem::create([
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'product_id' => $produit->id,
            'quantity' => 1,
            'amount' => $produit->price,
            'status' => 'Success',
        ]);

        $corps = [
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'delivery_address' => 'Akwa',
            'delivery_fees' => 0,
        ];

        $premiere = $this->postJson('/api/v1.0/createOrder', $corps);
        $seconde = $this->postJson('/api/v1.0/createOrder', $corps);

        $this->assertSame($premiere->json('data.id'), $seconde->json('data.id'));
        $seconde->assertJsonPath('doublon', true);
    }
}
