<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\order_detail;
use App\Models\Product;
use App\Models\User;
use App\Support\PanierDeCommande;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modification complète d'une commande depuis l'administration.
 *
 * L'écran ne permettait que de changer le statut : une quantité mal comprise au
 * téléphone ou un article en rupture obligeait à tout annuler et ressaisir, en
 * perdant l'historique et l'agent déjà attribué.
 */
class ModificationCommandeAdminTest extends TestCase
{
    private ?Cart $panier = null;
    private ?order_detail $commande = null;
    private array $produits = [];

    private function staff(): User
    {
        $staff = User::where('role', 'admin')->first();

        if (! $staff) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        return $staff;
    }

    private function produit(string $nom, int $prix): Product
    {
        $produit = Product::create([
            'name' => $nom, 'price' => $prix, 'stock_init' => 10,
            'status' => 'Success', 'is_complement' => false,
        ]);

        $this->produits[] = $produit->id;

        return $produit;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $client = User::first();
        $this->panier = Cart::create(['user_id' => $client->id, 'total_amount' => 0]);

        DB::table('order_details')->insert([
            'ref' => 'ESSAI_ADMIN_PANIER', 'id_user' => $client->id,
            'id_cart' => $this->panier->id, 'status' => 'process',
            'price' => 0, 'panier_price' => 0, 'delivery_fees' => 500,
            'address' => 'Essai', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->commande = order_detail::where('ref', 'ESSAI_ADMIN_PANIER')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CartItem::where('cart_id', $this->panier?->id)->delete();
        order_detail::where('ref', 'ESSAI_ADMIN_PANIER')->delete();
        $this->panier?->delete();
        Product::whereIn('id', $this->produits)->forceDelete();

        parent::tearDown();
    }

    private function regle(): PanierDeCommande
    {
        return app(PanierDeCommande::class);
    }

    public function test_l_ecran_propose_de_modifier_le_panier(): void
    {
        $reponse = $this->actingAs($this->staff())->get('/dashboard/commandes');

        $reponse->assertOk();
        $reponse->assertSeeText('Modifier le panier');
        $reponse->assertSee('ouvrirPanier', false);

        /*
         | Le détail du panier n'est rendu qu'une fois déplié : on vérifie dans
         | la source que les trois gestes existent bien derrière le bouton —
         | changer une quantité, retirer une ligne, ajouter un produit.
         */
        $source = file_get_contents(base_path('resources/views/pages/dashboard/commandes.blade.php'));

        foreach (['changerQuantite', 'retirerLigne', 'ajouterProduit'] as $geste) {
            $this->assertStringContainsString($geste, $source, "Le geste $geste a disparu de l'écran.");
        }
    }

    public function test_la_quantite_se_modifie_et_le_total_suit(): void
    {
        $poulet = $this->produit('Poulet admin', 3000);
        $ligne = $this->regle()->ajouter($this->commande, $poulet, 1);

        $this->regle()->definirQuantite($this->commande->fresh(), $ligne, 3);

        $this->commande->refresh();

        $this->assertSame(3, (int) $ligne->fresh()->quantity);
        $this->assertSame(9000, (int) $this->commande->panier_price);
        // Le total suit la même règle qu'à la création : panier + livraison.
        $this->assertSame(9500, (int) $this->commande->price);
    }

    /*
     | Une quantité tombée à zéro retire la ligne.
     |
     | Laisser un article à zéro dans une commande n'a pas de sens et se relit
     | comme une erreur de saisie.
     */
    public function test_une_quantite_nulle_retire_la_ligne(): void
    {
        $poulet = $this->produit('Poulet à zéro', 3000);
        $ligne = $this->regle()->ajouter($this->commande, $poulet, 1);

        $this->regle()->definirQuantite($this->commande->fresh(), $ligne, 0);

        $this->assertNull(CartItem::find($ligne->id));
        $this->assertSame(0, (int) $this->commande->fresh()->panier_price);
    }

    public function test_plusieurs_produits_se_cumulent_correctement(): void
    {
        $poulet = $this->produit('Poulet cumul', 3000);
        $frites = $this->produit('Frites cumul', 500);

        $this->regle()->ajouter($this->commande, $poulet, 2);
        $this->regle()->ajouter($this->commande, $frites, 3);

        $this->commande->refresh();

        $this->assertSame(2 * 3000 + 3 * 500, (int) $this->commande->panier_price);
        $this->assertSame(2, CartItem::where('cart_id', $this->panier->id)->count());
    }

    /*
     | Le total est recalculé depuis les lignes, jamais ajusté du montant touché.
     |
     | Une commande déjà corrigée au poids dériverait sinon un peu plus à chaque
     | geste.
     */
    public function test_le_total_est_recalcule_depuis_les_lignes(): void
    {
        $poulet = $this->produit('Poulet dérive', 3000);
        $this->regle()->ajouter($this->commande, $poulet, 2);

        // Montant faussé à la main, comme après une correction au poids.
        $this->commande->update(['price' => 99999, 'panier_price' => 77777]);

        $this->regle()->recalculer($this->commande->fresh());

        $this->commande->refresh();

        $this->assertSame(6000, (int) $this->commande->panier_price);
        $this->assertSame(6500, (int) $this->commande->price);
    }

    /** Une commande close ne se corrige plus. */
    public function test_une_commande_close_n_est_pas_modifiable(): void
    {
        $this->commande->update(['status' => 'Success']);

        $this->assertFalse($this->regle()->modifiable($this->commande->fresh()));
    }

    /** Une course de coursier n'a pas de panier. */
    public function test_une_course_n_est_pas_modifiable(): void
    {
        $this->commande->update(['id_cart' => null]);

        $this->assertFalse($this->regle()->modifiable($this->commande->fresh()));
        $this->assertTrue($this->regle()->lignes($this->commande->fresh())->isEmpty());
    }

    /*
     | Le poids saisi devient faux dès que le panier change.
     |
     | Le laisser ferait croire que le montant en découle encore.
     */
    public function test_modifier_le_panier_efface_le_poids(): void
    {
        $poulet = $this->produit('Poulet pesé', 3000);
        $this->commande->update(['poids_kg' => 2.5]);

        $this->regle()->ajouter($this->commande->fresh(), $poulet, 1);

        $this->assertNull($this->commande->fresh()->poids_kg);
    }
}
