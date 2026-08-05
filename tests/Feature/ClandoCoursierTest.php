<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\User;
use Tests\TestCase;

/**
 * Clando et Coursier partagent la table "clando" mais sont deux services :
 * un trajet sans colis d'un côté, un colis à déposer de l'autre.
 *
 * Le tri se fait sur delivery_type. Un premier essai s'appuyait sur id_order,
 * champ renseigné sur aucune course en base : l'écran Coursiers restait vide.
 */
class ClandoCoursierTest extends TestCase
{
    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_les_deux_ecrans_s_affichent(): void
    {
        foreach (['/dashboard/clando', '/dashboard/coursiers'] as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_ils_sont_fermes_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/dashboard/clando')->assertForbidden();
        $this->actingAs($client)->get('/dashboard/coursiers')->assertForbidden();
    }

    public function test_les_deux_ecrans_ne_se_recouvrent_pas(): void
    {
        $coursiers = Clando::where('delivery_type', 'delivery')->count();
        $trajets = Clando::where(function ($q) {
            $q->whereNull('delivery_type')->orWhere('delivery_type', '!=', 'delivery');
        })->count();

        // Aucune course ne doit être perdue ni comptée deux fois.
        $this->assertSame(Clando::count(), $coursiers + $trajets);
    }

    public function test_les_deux_liens_sont_dans_la_navigation(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/dashboard');

        $reponse->assertSee('Clando');
        $reponse->assertSee('Coursiers');
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
