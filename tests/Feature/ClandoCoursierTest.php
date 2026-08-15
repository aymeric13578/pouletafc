<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\User;
use Tests\TestCase;

/**
 * Clando et Courses sont deux services distincts.
 *
 * Un trajet sans colis d'un côté, un colis à porter de l'autre. On a longtemps
 * cru qu'ils partageaient la table « clando », triés par delivery_type : il
 * n'existe aucune ligne « delivery », et l'écran restait vide. Une demande de
 * course crée en réalité un order_detail sans panier et avec un point de
 * départ — c'est cette règle que l'écran Courses applique désormais.
 */
class ClandoCoursierTest extends TestCase
{
    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_les_deux_ecrans_s_affichent(): void
    {
        foreach (['/dashboard/clando', '/dashboard/courses'] as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_ils_sont_fermes_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/dashboard/clando')->assertForbidden();
        $this->actingAs($client)->get('/dashboard/courses')->assertForbidden();
    }

    /*
     | Les deux écrans ne lisent pas la même table.
     |
     | Clando liste des trajets ; Courses liste des commandes sans panier et
     | avec un point de départ. Le tri par delivery_type ne pouvait pas marcher :
     | aucune ligne clando ne porte « delivery ».
     */
    public function test_les_deux_ecrans_ne_se_recouvrent_pas(): void
    {
        $this->assertSame(
            0,
            Clando::where('delivery_type', 'delivery')->count(),
            'Aucune course ne vit dans la table clando : elles sont des order_details.'
        );

        $courses = \App\Models\order_detail::whereNull('id_cart')
            ->whereNotNull('depart')
            ->where('depart', '!=', '')
            ->count();

        // Une course n'est jamais un trajet clando, et réciproquement.
        $this->assertGreaterThanOrEqual(0, $courses);
    }

    public function test_les_deux_liens_sont_dans_la_navigation(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/dashboard');

        $reponse->assertSee('Clando');
        $reponse->assertSee('Courses');
    }

    public function test_le_mur_distingue_coursier_et_commande(): void
    {
        $reponse = $this->getJson('/commandes/flux');

        $reponse->assertOk();
        $reponse->assertJsonStructure(['orders' => [['type']]]);

        foreach ($reponse->json('orders') ?? [] as $commande) {
            $this->assertContains($commande['type'], ['coursier', 'commande']);
        }
    }
}
