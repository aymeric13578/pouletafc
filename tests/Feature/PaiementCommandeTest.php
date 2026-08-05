<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\order_detail;
use Tests\TestCase;

/**
 * Statut de paiement des commandes.
 *
 * La colonne status_paiement existait déjà — enum('pending','Success','failed') —
 * mais n'était affichée ni modifiable nulle part. Le tunnel du site y écrivait
 * 'paid'/'unpaid', valeurs hors énumération que MySQL refuse.
 */
class PaiementCommandeTest extends TestCase
{
    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_le_mur_expose_le_statut_de_paiement(): void
    {
        $this->getJson('/commandes/flux')
            ->assertOk()
            ->assertJsonStructure(['orders' => [['status_paiement']]]);
    }

    public function test_on_peut_marquer_une_commande_payee_depuis_le_mur(): void
    {
        $commande = order_detail::where('status_paiement', '!=', 'Success')->firstOrFail();
        $initial = $commande->status_paiement;

        $this->postJson("/commandes/{$commande->id}/paiement", ['status_paiement' => 'Success'])
            ->assertOk();

        $this->assertSame('Success', $commande->fresh()->status_paiement);

        $commande->update(['status_paiement' => $initial]);
    }

    public function test_une_valeur_hors_enumeration_est_refusee(): void
    {
        // 'paid' n'existe pas dans l'enum : l'accepter écrirait une chaîne vide.
        $commande = order_detail::firstOrFail();
        $initial = $commande->status_paiement;

        $this->postJson("/commandes/{$commande->id}/paiement", ['status_paiement' => 'paid'])
            ->assertStatus(422);

        $this->assertSame($initial, $commande->fresh()->status_paiement);
    }

    public function test_le_detail_du_panier_indique_la_boutique_de_chaque_article(): void
    {
        /*
         * Un panier peut mêler plusieurs boutiques : le détail doit permettre de
         * voir laquelle fournit quoi.
         */
        $this->getJson('/commandes/flux')
            ->assertOk()
            ->assertJsonStructure(['orders' => [['shops', 'items']]]);
    }

    public function test_le_marchand_ne_voit_pas_les_coordonnees_du_client(): void
    {
        $boutique = Shop::whereNotNull('id_user')->whereHas('user')->firstOrFail();
        $marchand = User::findOrFail($boutique->id_user);

        $client = order_detail::whereHas('carts.cart_items.product', fn ($q) => $q->where('id_shop', $boutique->id))
            ->whereNotNull('id_user')
            ->with('user')
            ->first()?->user;

        if (! $client?->name) {
            $this->markTestSkipped('Aucune commande de cette boutique rattachée à un client identifié.');
        }

        // Le marchand constate que son produit a été commandé, sans accéder au
        // client : une commande peut mêler plusieurs boutiques.
        $this->actingAs($marchand)
            ->get('/merchand/commandes')
            ->assertOk()
            ->assertDontSee($client->name);
    }
}
