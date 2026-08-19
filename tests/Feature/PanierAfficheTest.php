<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ce qui est affiché doit être ce qui est facturé.
 *
 * Retirer un article ne l'efface pas : sa ligne passe en « failed ». Les
 * relations du panier ne filtraient rien, si bien que tout ce qui avait été
 * retiré continuait de s'afficher — sur le mur du comptoir comme dans
 * l'historique du client.
 *
 * Constaté sur une commande du 19 août : treize lignes affichées totalisant
 * 30 000 F pour une commande de 2 500 F. Le montant était juste, il ne comptait
 * que les lignes vivantes ; c'est la liste qui mentait.
 */
class PanierAfficheTest extends TestCase
{
    private array $paniers = [];

    protected function tearDown(): void
    {
        if ($this->paniers) {
            DB::table('cart_items')->whereIn('cart_id', $this->paniers)->delete();
            DB::table('carts')->whereIn('id', $this->paniers)->delete();
        }

        parent::tearDown();
    }

    public function test_un_article_retire_ne_s_affiche_plus(): void
    {
        $client = User::first();
        $produit = Product::first();

        if (! $client || ! $produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'pending', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        $garde = CartItem::create([
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'product_id' => $produit->id,
            'quantity' => 1,
            'amount' => 2000,
            'status' => 'Success',
        ]);

        CartItem::create([
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'product_id' => $produit->id,
            'quantity' => 1,
            'amount' => 28000,
            // Retiré par le client : la ligne survit en base, elle ne doit pas
            // survivre à l'écran.
            'status' => 'failed',
        ]);

        $relu = Cart::with('cart_items')->find($panier->id);

        $this->assertCount(1, $relu->cart_items, "Seule la ligne vivante doit être affichée.");
        $this->assertSame($garde->id, $relu->cart_items->first()->id);

        // La somme affichée correspond alors à ce que le client paiera.
        $this->assertSame(
            2000,
            (int) $relu->cart_items->sum(fn ($ligne) => $ligne->amount * $ligne->quantity)
        );
    }

    public function test_les_deux_relations_disent_la_meme_chose(): void
    {
        $client = User::first();
        $produit = Product::first();

        if (! $client || ! $produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'pending', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        CartItem::create([
            'user_id' => $client->id, 'cart_id' => $panier->id, 'product_id' => $produit->id,
            'quantity' => 1, 'amount' => 1000, 'status' => 'Success',
        ]);
        CartItem::create([
            'user_id' => $client->id, 'cart_id' => $panier->id, 'product_id' => $produit->id,
            'quantity' => 1, 'amount' => 1000, 'status' => 'failed',
        ]);

        $relu = Cart::with(['cart_items', 'cartItems'])->find($panier->id);

        // Elles sont employées indifféremment dans le code : elles n'ont aucune
        // raison de ne pas donner le même panier.
        $this->assertSame($relu->cart_items->count(), $relu->cartItems->count());
    }
}
