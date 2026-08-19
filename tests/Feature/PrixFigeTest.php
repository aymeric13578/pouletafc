<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\order_detail;
use App\Support\MontantDeCommande;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Changer le prix d'un produit ne doit rien changer aux commandes déjà passées.
 *
 * Le prix d'une ligne de panier est capturé au moment de l'ajout, dans
 * « cart_items.amount ». Les écrans retombaient pourtant sur le tarif courant du
 * catalogue quand la ligne ne portait pas de « price » — ce qui est le cas de
 * toutes celles créées par l'application. Une hausse de prix aurait donc réécrit
 * le montant des commandes passées.
 */
class PrixFigeTest extends TestCase
{
    private array $paniers = [];

    private ?Product $produit = null;

    private $prixInitial = null;

    protected function tearDown(): void
    {
        if ($this->produit && $this->prixInitial !== null) {
            Product::where('id', $this->produit->id)->update(['price' => $this->prixInitial]);
        }

        if ($this->paniers) {
            DB::table('order_details')->whereIn('id_cart', $this->paniers)->delete();
            DB::table('cart_items')->whereIn('cart_id', $this->paniers)->delete();
            DB::table('carts')->whereIn('id', $this->paniers)->delete();
        }

        parent::tearDown();
    }

    public function test_une_hausse_de_prix_ne_touche_pas_une_commande_passee(): void
    {
        $client = User::first();
        $this->produit = Product::first();

        if (! $client || ! $this->produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $this->prixInitial = $this->produit->price;

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'Success', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        // Deux unités à 2 000 F, prix capturé au moment de l'ajout.
        CartItem::create([
            'user_id' => $client->id,
            'cart_id' => $panier->id,
            'product_id' => $this->produit->id,
            'quantity' => 2,
            'amount' => 2000,
            'status' => 'Success',
        ]);

        $commande = order_detail::create([
            'id_user' => $client->id,
            'id_cart' => $panier->id,
            'ref' => 'TEST_' . uniqid(),
            'price' => 4000,
            'panier_price' => 4000,
            'delivery_fees' => 0,
            'status' => 'pending',
        ]);

        // Le catalogue augmente après coup.
        Product::where('id', $this->produit->id)->update(['price' => 9999]);

        $commande->load('carts.cart_items.product');

        $this->assertSame(4000, MontantDeCommande::panier($commande), "L'ancienne commande garde son prix.");
        $this->assertSame(4000, MontantDeCommande::total($commande));
    }

    public function test_le_mur_affiche_le_prix_capture_et_non_le_tarif_courant(): void
    {
        $client = User::first();
        $this->produit = Product::first();

        if (! $client || ! $this->produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $this->prixInitial = $this->produit->price;

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'Success', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        CartItem::create([
            'user_id' => $client->id, 'cart_id' => $panier->id, 'product_id' => $this->produit->id,
            'quantity' => 1, 'amount' => 2000, 'status' => 'Success',
        ]);

        $commande = order_detail::create([
            'id_user' => $client->id, 'id_cart' => $panier->id, 'ref' => 'TEST_' . uniqid(),
            'price' => 2000, 'panier_price' => 2000, 'delivery_fees' => 0, 'status' => 'pending',
        ]);

        Product::where('id', $this->produit->id)->update(['price' => 9999]);

        $ligne = collect($this->getJson('/commandes/flux')->json('orders'))
            ->firstWhere('id', $commande->id);

        $this->assertNotNull($ligne);
        $this->assertSame(2000, $ligne['items'][0]['unit_price'], 'Le prix unitaire doit rester celui de la commande.');
        $this->assertSame(2000, $ligne['price']);
    }
}
