<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quand le montant facturé ne couvre pas le panier, le comptoir doit le voir et
 * pouvoir trancher.
 *
 * Constaté en production : REF_C7suxuZ3bA facturait 2 500 F pour treize articles
 * en valant 30 000 ; REF_aW4h7x0r2K, 13 500 F pour vingt-quatre articles en
 * valant 77 500. Ces paniers portent la trace de plusieurs compositions
 * successives — « Poulet Pané, Frite de plantain » y revient six fois de suite.
 */
class RealignementDuTotalTest extends TestCase
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

    private function commandeDesalignee(): order_detail
    {
        $client = User::first();
        $produit = Product::first();

        if (! $client || ! $produit) {
            $this->markTestSkipped('Base sans client ou sans produit.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'status' => 'Success', 'total_amount' => 0]);
        $this->paniers[] = $panier->id;

        foreach ([2000, 500, 4000, 3500] as $montant) {
            CartItem::create([
                'user_id' => $client->id,
                'cart_id' => $panier->id,
                'product_id' => $produit->id,
                'quantity' => 1,
                'amount' => $montant,
                'status' => 'Success',
            ]);
        }

        // La commande n'a retenu que les deux premiers articles : c'est la
        // situation observée.
        return order_detail::create([
            'id_user' => $client->id,
            'id_cart' => $panier->id,
            'ref' => 'TEST_' . uniqid(),
            'price' => 2500,
            'panier_price' => 2500,
            'delivery_fees' => 0,
            'status' => 'pending',
        ]);
    }

    public function test_l_ecart_est_expose_au_comptoir(): void
    {
        $commande = $this->commandeDesalignee();

        $ligne = collect($this->getJson('/commandes/flux')->json('orders'))
            ->firstWhere('id', $commande->id);

        $this->assertNotNull($ligne, 'La commande doit apparaître sur le mur.');

        // Le montant affiché est la somme des articles, pas la valeur figée en
        // base : c'est le panier qu'on a sous les yeux, il ne peut pas mentir.
        $this->assertSame(10000, $ligne['panier_price']);
        $this->assertSame(10000, $ligne['panier_calcule']);

        // La valeur enregistrée reste accessible — elle sert la comptabilité —
        // et sa divergence est signalée pour que le comptoir puisse trancher.
        $this->assertSame(2500, $ligne['price_enregistre']);
        $this->assertTrue($ligne['montant_diverge']);
    }

    public function test_le_comptoir_peut_aligner_le_total(): void
    {
        $commande = $this->commandeDesalignee();

        $this->postJson("/commandes/{$commande->id}/recalcul")
            ->assertOk()
            ->assertJsonPath('recalcul.ok', true);

        $commande->refresh();

        $this->assertSame(10000, (int) $commande->panier_price);
        $this->assertSame(10000, (int) $commande->price);
        $this->assertSame(4, (int) $commande->qty);
    }

    public function test_les_frais_de_livraison_restent_ajoutes(): void
    {
        $commande = $this->commandeDesalignee();
        $commande->update(['delivery_fees' => 500]);

        $this->postJson("/commandes/{$commande->id}/recalcul")->assertOk();

        $commande->refresh();

        // Le panier vaut 10 000, la livraison 500 : la commande vaut 10 500.
        $this->assertSame(10000, (int) $commande->panier_price);
        $this->assertSame(10500, (int) $commande->price);
    }
}
