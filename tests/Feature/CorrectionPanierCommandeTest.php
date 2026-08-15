<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\order_detail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correction du panier d'une commande par le comptoir.
 *
 * Un article en rupture ou commandé par erreur obligeait à tout annuler et à
 * ressaisir, en perdant l'historique et l'agent déjà attribué.
 */
class CorrectionPanierCommandeTest extends TestCase
{
    private ?Cart $panier = null;
    private ?order_detail $commande = null;
    private array $produits = [];

    private function produit(string $nom, int $prix, bool $complement = false): Product
    {
        $produit = Product::create([
            'name' => $nom, 'price' => $prix, 'stock_init' => 10,
            'status' => 'Success', 'is_complement' => $complement,
        ]);

        $this->produits[] = $produit->id;

        return $produit;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $client = User::first();

        if (! $client) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }

        $this->panier = Cart::create(['user_id' => $client->id, 'total_amount' => 0]);

        DB::table('order_details')->insert([
            'ref' => 'ESSAI_CORRECTION', 'id_user' => $client->id, 'id_cart' => $this->panier->id,
            'status' => 'process', 'price' => 0, 'panier_price' => 0, 'delivery_fees' => 500,
            'address' => 'Essai', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->commande = order_detail::where('ref', 'ESSAI_CORRECTION')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CartItem::where('cart_id', $this->panier?->id)->delete();
        order_detail::where('ref', 'ESSAI_CORRECTION')->delete();
        $this->panier?->delete();

        DB::table('product_complement')
            ->whereIn('product_id', $this->produits)
            ->orWhereIn('complement_id', $this->produits)
            ->delete();

        Product::whereIn('id', $this->produits)->forceDelete();

        parent::tearDown();
    }

    public function test_ajouter_un_article_met_le_total_a_jour(): void
    {
        $poulet = $this->produit('Poulet', 3000);

        $this->postJson(route('orders.board.item.add', $this->commande->id), [
            'product_id' => $poulet->id,
            'quantity' => 2,
        ])->assertOk();

        $this->commande->refresh();

        $this->assertSame(6000, (int) $this->commande->panier_price);
        // Le total suit la même règle qu'à la création : panier + livraison.
        $this->assertSame(6500, (int) $this->commande->price);
    }

    /*
     | Le même article deux fois donne une seule ligne de quantité double.
     |
     | Deux lignes identiques se corrigent mal et s'additionnent mal à l'œil.
     */
    public function test_ajouter_deux_fois_le_meme_article_cumule_la_quantite(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $url = route('orders.board.item.add', $this->commande->id);

        $this->postJson($url, ['product_id' => $poulet->id, 'quantity' => 1])->assertOk();
        $this->postJson($url, ['product_id' => $poulet->id, 'quantity' => 2])->assertOk();

        $lignes = CartItem::where('cart_id', $this->panier->id)->get();

        $this->assertCount(1, $lignes);
        $this->assertSame(3, (int) $lignes->first()->quantity);
    }

    public function test_retirer_un_article_met_le_total_a_jour(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $frites = $this->produit('Frites', 1000, complement: true);

        $url = route('orders.board.item.add', $this->commande->id);
        $this->postJson($url, ['product_id' => $poulet->id])->assertOk();
        $this->postJson($url, ['product_id' => $frites->id])->assertOk();

        $ligneFrites = CartItem::where('cart_id', $this->panier->id)
            ->where('product_id', $frites->id)
            ->firstOrFail();

        $this->deleteJson(route('orders.board.item.remove', [$this->commande->id, $ligneFrites->id]))
            ->assertOk();

        $this->commande->refresh();

        $this->assertSame(3000, (int) $this->commande->panier_price);
        $this->assertSame(3500, (int) $this->commande->price);
        $this->assertSame(1, CartItem::where('cart_id', $this->panier->id)->count());
    }

    /*
     | Une commande close ne se corrige plus.
     |
     | Elle a été livrée et encaissée : en changer le montant après coup
     | fausserait les comptes sans laisser de trace.
     */
    public function test_une_commande_close_refuse_toute_correction(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $this->commande->update(['status' => 'Success']);

        $this->postJson(route('orders.board.item.add', $this->commande->id), [
            'product_id' => $poulet->id,
        ])->assertStatus(409);

        $this->assertSame(0, CartItem::where('cart_id', $this->panier->id)->count());
    }

    /** Une course de coursier n'a pas de panier : on n'y ajoute rien. */
    public function test_une_course_sans_panier_refuse_l_ajout(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $this->commande->update(['id_cart' => null]);

        $this->postJson(route('orders.board.item.add', $this->commande->id), [
            'product_id' => $poulet->id,
        ])->assertStatus(422);
    }

    /** L'article retiré doit appartenir à cette commande, pas à une autre. */
    public function test_un_article_etranger_ne_peut_pas_etre_retire(): void
    {
        $autrePanier = Cart::create(['user_id' => $this->commande->id_user, 'total_amount' => 0]);
        $poulet = $this->produit('Poulet', 3000);

        // Composée par la règle partagée : une ligne fabriquée à la main
        // omettrait les colonnes obligatoires en production.
        $ligneEtrangere = CartItem::create(
            app(\App\Support\PanierDeCommande::class)
                ->donneesDeLigne($autrePanier->id, $this->commande->id_user, $poulet, 1)
        );

        try {
            $this->deleteJson(route('orders.board.item.remove', [$this->commande->id, $ligneEtrangere->id]))
                ->assertStatus(404);

            $this->assertNotNull(CartItem::find($ligneEtrangere->id));
        } finally {
            $ligneEtrangere->delete();
            $autrePanier->delete();
        }
    }

    /** Le comptoir doit pouvoir demander ce qui accompagne le panier en cours. */
    public function test_le_comptoir_obtient_les_complements_du_panier(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $frites = $this->produit('Frites', 1000, complement: true);
        $poulet->complements()->attach($frites->id);

        $this->postJson(route('orders.board.item.add', $this->commande->id), [
            'product_id' => $poulet->id,
        ])->assertOk();

        $this->getJson(route('orders.board.complements', $this->commande->id))
            ->assertOk()
            ->assertJsonPath('demander', true)
            ->assertJsonPath('tous_en_proposent', true)
            ->assertJsonPath('complements.0.name', 'Frites');
    }
}
