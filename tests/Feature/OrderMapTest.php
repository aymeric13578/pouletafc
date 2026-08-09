<?php

namespace Tests\Feature;

use App\Models\order_detail;
use App\Models\User;
use Tests\TestCase;

/**
 * Carte des livraisons, atteinte depuis le mur des commandes.
 */
class OrderMapTest extends TestCase
{
    public function test_la_carte_s_affiche_pour_l_equipe(): void
    {
        $staff = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($staff)->get('/commandes/carte')->assertOk();
    }

    public function test_la_carte_est_accessible_sans_connexion(): void
    {
        // Même accès libre que le mur : l'écran tourne sans session ouverte.
        $this->get('/commandes/carte')->assertOk();
        $this->getJson('/commandes/carte/flux')->assertOk();
    }

    public function test_la_carte_ne_masque_pas_le_mur_des_commandes(): void
    {
        /*
         * /commandes/carte et /commandes/{order}/statut cohabitent. Une route mal
         * ordonnée ferait passer « carte » pour un identifiant de commande.
         */
        $this->get('/commandes')->assertOk();
        $this->getJson('/commandes/flux')->assertOk();
    }

    public function test_le_flux_expose_ce_dont_la_carte_a_besoin(): void
    {
        $this->getJson('/commandes/carte/flux')
            ->assertOk()
            ->assertJsonStructure([
                'orders',
                'agents',
                'agents_disponibles',
                'stats' => ['actives', 'en_attente', 'du_jour', 'ca_jour', 'agents_actifs'],
                'latest_id',
                'server_time',
                'server_date',
            ]);
    }

    public function test_le_flux_n_est_jamais_mis_en_cache(): void
    {
        $entete = $this->getJson('/commandes/carte/flux')->assertOk()->headers->get('Cache-Control');

        foreach (['no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $entete);
        }
    }

    public function test_une_commande_porte_ses_trois_points_distincts(): void
    {
        /*
         * Boutique, livraison et agent ne se confondent pas : c'est justement
         * l'écart entre les deux premiers que l'écran sert à juger.
         */
        $commande = order_detail::query()->firstOrFail();
        $etat = $commande->only(['latShop', 'lonShop', 'latitude', 'longitude', 'status']);

        $commande->update([
            'latShop' => 4.05,
            'lonShop' => 9.70,
            'latitude' => 4.06,
            'longitude' => 9.78,
            'status' => 'want',
        ]);

        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertNotNull($trouvee);
        $this->assertEqualsWithDelta(4.05, $trouvee['boutique']['lat'], 0.001);
        $this->assertEqualsWithDelta(4.06, $trouvee['livraison']['lat'], 0.001);

        order_detail::where('id', $commande->id)->update($etat);
    }

    public function test_une_coordonnee_a_zero_ne_produit_pas_de_point(): void
    {
        // Un zéro tombe au large du golfe de Guinée : il ferait dézoomer la carte
        // sur l'Atlantique.
        $commande = order_detail::query()->firstOrFail();
        $etat = $commande->only(['latitude', 'longitude', 'status']);

        $commande->update(['latitude' => 0, 'longitude' => 0, 'status' => 'want']);

        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertNotNull($trouvee, 'La commande reste listée même sans point exploitable.');
        $this->assertNull($trouvee['livraison']);

        order_detail::where('id', $commande->id)->update($etat);
    }

    public function test_une_commande_deja_prise_n_est_pas_attribuable(): void
    {
        $commande = order_detail::query()->firstOrFail();
        $etat = $commande->only(['id_agent', 'status']);

        // order_details.id_agent porte une clé étrangère vers users : un
        // identifiant inventé serait rejeté par la base, pas par le contrôleur.
        $occupant = User::query()->value('id');

        $commande->update(['id_agent' => $occupant, 'status' => 'want']);

        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertFalse($trouvee['attribuable'], "L'écran s'appuie sur ce drapeau pour masquer le bouton.");

        $commande->update(['id_agent' => null, 'status' => 'want']);

        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertTrue($trouvee['attribuable']);

        order_detail::where('id', $commande->id)->update($etat);
    }

    public function test_un_agent_sans_position_n_est_pas_place_sur_la_carte(): void
    {
        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();

        foreach ($charge['agents'] as $agent) {
            $this->assertNotNull($agent['lat']);
            $this->assertNotNull($agent['lon']);
        }
    }

    public function test_latest_id_ignore_les_filtres_d_affichage(): void
    {
        $charge = $this->getJson('/commandes/carte/flux')->assertOk()->json();

        $this->assertSame((int) order_detail::max('id'), $charge['latest_id']);
    }
}
