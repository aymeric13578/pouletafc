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

    public function test_le_mur_est_ferme_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/commandes')->assertForbidden();
        $this->actingAs($client)->get('/commandes/flux')->assertForbidden();
    }

    public function test_le_mur_est_ferme_aux_visiteurs(): void
    {
        // L'écran affiche noms, téléphones et adresses : il ne doit jamais être public.
        $this->get('/commandes')->assertRedirect('/login');
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

    public function test_les_commandes_en_attente_remontent_et_les_livrees_descendent(): void
    {
        $statuts = collect($this->actingAs($this->staff())->getJson('/commandes/flux')->json('orders'))
            ->pluck('status');

        $rang = fn (string $statut) => match (true) {
            in_array($statut, ['pending', 'want'], true) => 0,
            in_array($statut, ['process', 'take'], true) => 1,
            $statut === 'Success' => 2,
            default => 3,
        };

        $rangs = $statuts->map($rang)->all();
        $attendus = $rangs;
        sort($attendus);

        $this->assertSame($attendus, $rangs,
            "Les commandes à traiter doivent précéder celles en cours, puis les livrées.");
    }

    public function test_a_statut_egal_la_plus_recente_passe_devant(): void
    {
        $commandes = collect($this->actingAs($this->staff())->getJson('/commandes/flux')->json('orders'));

        $commandes->groupBy('status')->each(function ($groupe) {
            $ids = $groupe->pluck('id')->all();
            $triees = $ids;
            rsort($triees);

            $this->assertSame($triees, $ids);
        });
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
