<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Mur des commandes affiché en télévision.
 */
class OrderBoardTest extends TestCase
{
    protected function staff(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_le_mur_s_affiche_pour_l_equipe(): void
    {
        $this->actingAs($this->staff())->get('/commandes')->assertOk();
    }

    public function test_le_mur_est_accessible_sans_connexion(): void
    {
        /*
         * Accès libre voulu : l'écran tourne en continu dans le local, sans clavier
         * ni session ouverte. Conséquence assumée, noms, téléphones et adresses de
         * clients sont visibles de qui connaît l'URL.
         */
        $this->get('/commandes')->assertOk();
        $this->getJson('/commandes/flux')->assertOk();
    }

    public function test_un_operateur_peut_terminer_une_commande(): void
    {
        $commande = \App\Models\order_detail::whereIn('status', ['pending', 'want'])->firstOrFail();
        $statutInitial = $commande->status;

        $response = $this->postJson("/commandes/{$commande->id}/statut", ['status' => 'Success']);

        $response->assertOk();
        // La réponse renvoie le mur à jour : pas de seconde requête côté navigateur.
        $response->assertJsonStructure(['orders', 'stats', 'pagination']);
        $this->assertSame('Success', $commande->fresh()->status);

        $commande->update(['status' => $statutInitial]);
    }

    public function test_un_statut_inconnu_est_refuse(): void
    {
        $commande = \App\Models\order_detail::firstOrFail();
        $statutInitial = $commande->status;

        $this->postJson("/commandes/{$commande->id}/statut", ['status' => 'nimporte-quoi'])
            ->assertStatus(422);

        $this->assertSame($statutInitial, $commande->fresh()->status);
    }

    public function test_le_flux_renvoie_les_commandes_et_les_compteurs(): void
    {
        $response = $this->actingAs($this->staff())->getJson('/commandes/flux');

        $response->assertOk();
        $response->assertJsonStructure([
            'orders' => [['id', 'ref', 'price', 'status', 'items', 'items_count', 'created_label']],
            'stats' => ['total', 'actives', 'livrees', 'ca_jour', 'du_jour'],
            'server_time',
        ]);
    }

    public function test_le_flux_n_est_jamais_mis_en_cache(): void
    {
        // L'écran tourne des jours : un flux mis en cache le figerait sur
        // d'anciennes commandes sans que personne ne s'en aperçoive.
        $response = $this->actingAs($this->staff())->getJson('/commandes/flux');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_le_flux_est_limite_a_douze_commandes(): void
    {
        $response = $this->actingAs($this->staff())->getJson('/commandes/flux');

        $this->assertLessThanOrEqual(12, count($response->json('orders')));
    }

    public function test_le_flux_expose_le_detail_du_panier_et_l_agent(): void
    {
        // L'œil du tableau ouvre un panneau de détail : tout ce qu'il affiche doit
        // déjà être dans le flux, sinon chaque ouverture coûterait une requête.
        $response = $this->actingAs($this->staff())->getJson('/commandes/flux');

        $response->assertOk();
        $response->assertJsonStructure([
            'orders' => [[
                'whatsapp',
                'email',
                'created_full',
                'items' => [['name', 'quantity', 'unit_price', 'amount']],
            ]],
        ]);
    }

    public function test_les_commandes_sont_triees_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $ids = collect($this->actingAs($this->staff())->getJson('/commandes/flux')->json('orders'))
            ->pluck('id')
            ->all();

        $attendus = $ids;
        rsort($attendus);

        $this->assertSame($attendus, $ids, 'La commande la plus récente doit arriver en premier.');
    }

    public function test_changer_le_statut_ne_deplace_pas_la_commande(): void
    {
        /*
         * Un tri par statut avait été essayé : prendre une commande la faisait
         * changer de groupe et disparaître de l'écran, renvoyée derrière la
         * centaine de commandes en attente. Le tri étant désormais purement
         * chronologique, une ligne sur laquelle on agit reste à sa place.
         */
        $commande = \App\Models\order_detail::whereIn('status', ['pending', 'want'])->firstOrFail();
        $statutInitial = $commande->status;

        $avant = collect($this->getJson('/commandes/flux')->json('orders'))
            ->search(fn ($o) => $o['id'] === $commande->id);

        $apres = collect($this->postJson("/commandes/{$commande->id}/statut", ['status' => 'process'])->json('orders'))
            ->search(fn ($o) => $o['id'] === $commande->id);

        $commande->update(['status' => $statutInitial]);

        $this->assertSame($avant, $apres, 'La commande doit conserver sa position après un changement de statut.');
    }

    public function test_la_pagination_permet_d_atteindre_les_commandes_suivantes(): void
    {
        $page1 = $this->actingAs($this->staff())->getJson('/commandes/flux?page=1');
        $page1->assertOk();

        $pagination = $page1->json('pagination');
        $this->assertArrayHasKey('last_page', $pagination);
        $this->assertSame(1, $pagination['current_page']);

        if ($pagination['last_page'] < 2) {
            $this->markTestSkipped('Pas assez de commandes pour une deuxième page.');
        }

        $page2 = $this->actingAs($this->staff())->getJson('/commandes/flux?page=2');
        $page2->assertOk();
        $this->assertSame(2, $page2->json('pagination.current_page'));

        // Aucune commande ne doit apparaître sur deux pages.
        $ids1 = collect($page1->json('orders'))->pluck('id');
        $ids2 = collect($page2->json('orders'))->pluck('id');
        $this->assertEmpty($ids1->intersect($ids2));
    }

    public function test_le_flux_expose_l_identifiant_le_plus_recent_toutes_pages_confondues(): void
    {
        // Sans cet identifiant global, une commande arrivant pendant qu'on consulte
        // la page 3 ne déclencherait jamais la sonnerie.
        $response = $this->actingAs($this->staff())->getJson('/commandes/flux?page=2');

        $response->assertOk();
        $this->assertSame(
            (int) \App\Models\order_detail::max('id'),
            $response->json('latest_id')
        );
    }
}
