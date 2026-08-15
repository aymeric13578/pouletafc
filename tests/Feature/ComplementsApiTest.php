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

        /*
         | Lignes composées par la règle partagée plutôt qu'à la main : la table
         | porte en production des colonnes obligatoires que le schéma des
         | migrations ignore. Les contourner ici masquerait justement le défaut
         | qui a fait échouer l'ajout d'un produit au comptoir.
         */
        foreach ([$poulet, $poisson] as $plat) {
            CartItem::create(
                app(\App\Support\PanierDeCommande::class)
                    ->donneesDeLigne($this->panier->id, $this->client->id, $plat, 1)
            );
        }

        $data = $this->getJson('/api/v1.0/getCartComplements?id_user=' . $this->client->id)
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['demander']);
        $this->assertTrue($data['tous_en_proposent']);
        $this->assertCount(2, $data['complements'], 'Les frites communes ne doivent apparaître qu\'une fois.');
    }

    /*
     | Un complément s'ajoute par la voie normale.
     |
     | C'est tout l'intérêt d'en faire un produit : addToCartAndView tient à
     | jour le total du panier, ce qu'une route dédiée aurait vite oublié.
     */
    public function test_un_complement_s_ajoute_comme_n_importe_quel_produit(): void
    {
        $frites = $this->produit('Frites', 1000, complement: true);

        $reference = $this->client->ref;

        if (! $reference) {
            $this->markTestSkipped('Le compte de test n\'a pas de référence.');
        }

        /*
         | addToCartAndView est du code déjà en production ; il s'appuie sur des
         | colonnes que le schéma reconstruit depuis les migrations ne porte pas
         | toutes. Plutôt que d'inventer ces colonnes en local — ce qui masque
         | les écarts au lieu de les révéler — on ne joue ce test que là où le
         | schéma est complet.
         */
        if (! \Illuminate\Support\Facades\Schema::hasColumn('cart_items', 'status')) {
            $this->markTestSkipped('Schéma local incomplet : cart_items.status absent.');
        }

        $this->post('/api/v1.0/addToCartAndView', [
            'ref' => $reference,
            'product_id' => $frites->id,
            'quantity' => 1,
        ])->assertOk();

        $this->panier = Cart::where('user_id', $this->client->id)->orderByDesc('id')->first();

        $ligne = CartItem::where('cart_id', $this->panier->id)
            ->where('product_id', $frites->id)
            ->firstOrFail();

        $this->assertSame(1, (int) $ligne->quantity);
        $this->assertGreaterThan(0, (int) $this->panier->total_amount, 'Le total du panier doit suivre.');
    }

}
