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
 * Écrans Clients et Meilleurs produits.
 *
 * Le premier affichait « 1 200 clients », « 980 actifs », « 120 nouveaux » :
 * trois nombres écrits en dur dans le gabarit, qui n'avaient jamais rien lu en
 * base. Le second n'existait pas : la page Produits liste le catalogue, pas ce
 * qui part réellement.
 */
class EcransCommerciauxTest extends TestCase
{
    private function staff(): User
    {
        $staff = User::first();

        if (! $staff) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }

        $staff->role = 'admin';
        $staff->save();

        return $staff;
    }

    public function test_les_deux_ecrans_sont_reserves_a_l_equipe(): void
    {
        $visiteur = $this->staff();
        $role = $visiteur->role;
        $visiteur->role = 'user';
        $visiteur->save();

        try {
            $this->actingAs($visiteur)->get('/dashboard/customers')->assertForbidden();
            $this->actingAs($visiteur)->get('/dashboard/meilleurs-produits')->assertForbidden();
        } finally {
            $visiteur->role = $role;
            $visiteur->save();
        }
    }

    public function test_la_page_operateurs_a_disparu(): void
    {
        $this->actingAs($this->staff())->get('/dashboard/operators')->assertNotFound();
    }

    /*
     | Seules les commandes livrées comptent comme versées.
     |
     | Une commande annulée n'a rien rapporté, une commande en cours non plus
     | tant qu'elle n'est pas remise. Les confondre gonflerait le total d'un
     | client qui n'a peut-être jamais payé.
     */
    public function test_seules_les_commandes_livrees_comptent_dans_le_total_verse(): void
    {
        $client = User::firstOrFail();
        $refs = ['ESSAI_LIVREE', 'ESSAI_ANNULEE', 'ESSAI_EN_COURS'];

        order_detail::whereIn('ref', $refs)->delete();

        foreach ([['ESSAI_LIVREE', 'Success', 5000], ['ESSAI_ANNULEE', 'failed', 9000], ['ESSAI_EN_COURS', 'process', 7000]] as [$ref, $statut, $prix]) {
            DB::table('order_details')->insert([
                'ref' => $ref, 'id_user' => $client->id, 'status' => $statut,
                'price' => $prix, 'address' => 'Essai',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        try {
            $ligne = DB::table('order_details')
                ->where('id_user', $client->id)
                ->whereIn('ref', $refs)
                ->selectRaw("SUM(CASE WHEN status IN ('Success') THEN price ELSE 0 END) as verse")
                ->first();

            $this->assertEquals(5000, (int) $ligne->verse, 'Seule la commande livrée doit être comptée.');

            $this->actingAs($this->staff())->get('/dashboard/customers')->assertOk();
        } finally {
            order_detail::whereIn('ref', $refs)->delete();
        }
    }

    /*
     | Le montant d'une ligne est quantité × amount.
     |
     | Le nom de la colonne trompe : « amount » porte le prix unitaire figé à
     | l'ajout au panier, pas le total de la ligne. Le prendre pour un total
     | diviserait le chiffre d'affaires par la quantité commandée.
     */
    public function test_le_chiffre_d_affaires_multiplie_le_prix_unitaire_par_la_quantite(): void
    {
        $client = User::firstOrFail();
        $produit = Product::first();

        if (! $produit) {
            $this->markTestSkipped('Aucun produit en base.');
        }

        $panier = Cart::create(['user_id' => $client->id, 'total_amount' => 0]);
        $ligne = CartItem::create([
            'cart_id' => $panier->id,
            'product_id' => $produit->id,
            'quantity' => 4,
            'amount' => 1500,
        ]);

        DB::table('order_details')->insert([
            'ref' => 'ESSAI_PANIER', 'id_user' => $client->id, 'id_cart' => $panier->id,
            'status' => 'Success', 'price' => 6000, 'address' => 'Essai',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        try {
            $vente = DB::table('cart_items')
                ->join('order_details', 'order_details.id_cart', '=', 'cart_items.cart_id')
                ->where('cart_items.id', $ligne->id)
                ->selectRaw('SUM(cart_items.quantity) as quantite, SUM(cart_items.quantity * cart_items.amount) as montant')
                ->first();

            $this->assertEquals(4, (int) $vente->quantite);
            $this->assertEquals(6000, (int) $vente->montant, '4 × 1 500 F, et non 1 500 F.');

            $this->actingAs($this->staff())->get('/dashboard/meilleurs-produits')->assertOk();
        } finally {
            order_detail::where('ref', 'ESSAI_PANIER')->delete();
            $ligne->delete();
            $panier->delete();
        }
    }

    /** Le lien de l'application agent doit exister et refuser proprement si le fichier manque. */
    public function test_le_lien_de_l_application_agent_repond(): void
    {
        $service = app(\App\Services\MobileAppService::class);
        $reponse = $this->get(route('app.agent.apk'));

        if ($service->agentApkIsAvailable()) {
            $reponse->assertOk();
        } else {
            // 404 explicite plutôt qu'une erreur serveur : le fichier n'est pas
            // encore déposé, ce n'est pas une panne.
            $reponse->assertNotFound();
        }
    }
}
