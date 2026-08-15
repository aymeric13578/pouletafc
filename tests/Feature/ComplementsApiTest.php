<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Compléments côté application cliente.
 *
 * Deux moments : à l'ajout d'un produit — « ce poulet se prend avec des
 * frites » — et avant la validation du panier, où la liste offerte est l'union
 * sans doublon des compléments de tous les produits.
 */
class ComplementsApiTest extends TestCase
{
    private array $produits = [];
    private ?Cart $panier = null;
    private ?User $client = null;

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

        $this->client = User::first();

        if (! $this->client) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->panier) {
            CartItem::where('cart_id', $this->panier->id)->delete();
            $this->panier->delete();
        }

        DB::table('product_complement')
            ->whereIn('product_id', $this->produits)
            ->orWhereIn('complement_id', $this->produits)
            ->delete();

        Product::whereIn('id', $this->produits)->forceDelete();

        parent::tearDown();
    }

    public function test_un_produit_accompagne_annonce_ses_complements(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $frites = $this->produit('Frites', 1000, complement: true);
        $poulet->complements()->attach($frites->id);

        $this->getJson('/api/v1.0/getProductComplements?id_product=' . $poulet->id)
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.demander', true)
            ->assertJsonPath('data.complements.0.name', 'Frites')
            ->assertJsonPath('data.complements.0.price', 1000);
    }

    public function test_un_produit_sans_complement_ne_declenche_rien(): void
    {
        $nu = $this->produit('Boisson', 500);

        $this->getJson('/api/v1.0/getProductComplements?id_product=' . $nu->id)
            ->assertOk()
            ->assertJsonPath('data.demander', false)
            ->assertJsonCount(0, 'data.complements');
    }

    public function test_sans_identifiant_la_reponse_est_explicite(): void
    {
        $this->getJson('/api/v1.0/getProductComplements')
            ->assertOk()
            ->assertJsonPath('response', 400);
    }

    /** La liste du panier est l'union sans doublon des compléments. */
    public function test_le_panier_propose_une_liste_unique(): void
    {
        $poulet = $this->produit('Poulet', 3000);
        $poisson = $this->produit('Poisson', 3500);
        $frites = $this->produit('Frites', 1000, complement: true);
        $salade = $this->produit('Salade', 800, complement: true);

        $poulet->complements()->attach([$frites->id, $salade->id]);
        $poisson->complements()->attach([$frites->id]);

        $this->panier = Cart::create(['user_id' => $this->client->id, 'total_amount' => 0]);

        foreach ([$poulet, $poisson] as $plat) {
            CartItem::create([
                'cart_id' => $this->panier->id, 'product_id' => $plat->id,
                'quantity' => 1, 'amount' => $plat->price,
            ]);
        }

        $data = $this->getJson('/api/v1.0/getCartComplements?id_user=' . $this->client->id)
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['demander']);
        $this->assertTrue($data['tous_en_proposent']);
        $this->assertCount(2, $data['complements'], 'Les frites communes ne doivent apparaître qu\'une fois.');
    }

    public function test_ajouter_un_complement_le_place_dans_le_panier(): void
    {
        $frites = $this->produit('Frites', 1000, complement: true);

        $this->postJson('/api/v1.0/addComplementToCart', [
            'id_user' => $this->client->id,
            'id_complement' => $frites->id,
            'quantity' => 2,
        ])->assertOk()->assertJsonPath('response', 200);

        $this->panier = Cart::where('user_id', $this->client->id)->orderByDesc('id')->first();

        $ligne = CartItem::where('cart_id', $this->panier->id)
            ->where('product_id', $frites->id)
            ->firstOrFail();

        $this->assertSame(2, (int) $ligne->quantity);
        $this->assertSame(1000, (int) $ligne->amount, 'Le prix est figé à l\'ajout.');
    }

    /*
     | Cette route sert à accompagner un plat.
     |
     | Laisser passer n'importe quel produit en ferait une seconde voie d'ajout
     | au panier, avec ses propres règles à maintenir.
     */
    public function test_un_produit_ordinaire_est_refuse_comme_complement(): void
    {
        $poulet = $this->produit('Poulet', 3000);

        $this->postJson('/api/v1.0/addComplementToCart', [
            'id_user' => $this->client->id,
            'id_complement' => $poulet->id,
        ])->assertOk()->assertJsonPath('response', 422);
    }
}
