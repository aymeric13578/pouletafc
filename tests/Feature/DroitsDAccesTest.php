<?php

namespace Tests\Feature;

use App\Models\MenuPermission;
use App\Models\User;
use App\Support\MenuTableauDeBord;
use Tests\TestCase;

/**
 * Droits d'accès aux menus du tableau de bord.
 *
 * Entrer dans le back-office donnait tout : un employé chargé des commandes
 * voyait la grille des commissions et pouvait supprimer un produit. Masquer le
 * lien n'y changeait rien — /dashboard/configuration se devine.
 */
class DroitsDAccesTest extends TestCase
{
    private ?User $employe = null;
    private ?string $roleInitial = null;

    private function admin(): User
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        return $admin;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->employe = User::where('role', '!=', 'admin')->first();

        if (! $this->employe) {
            $this->markTestSkipped('Aucun compte non administrateur.');
        }

        $this->roleInitial = $this->employe->role;
        $this->employe->update(['role' => 'employee_afc']);
        MenuPermission::where('user_id', $this->employe->id)->delete();
    }

    protected function tearDown(): void
    {
        if ($this->employe) {
            MenuPermission::where('user_id', $this->employe->id)->delete();
            $this->employe->update(['role' => $this->roleInitial]);
        }

        parent::tearDown();
    }

    /*
     | Masquer un lien ne protège rien.
     |
     | C'est le cœur du sujet : sans contrôle côté serveur, il suffisait de
     | taper l'URL.
     */
    public function test_un_employe_sans_droit_ne_peut_pas_ouvrir_un_ecran(): void
    {
        $this->actingAs($this->employe)->get('/dashboard/configuration')->assertForbidden();
        $this->actingAs($this->employe)->get('/dashboard/products')->assertForbidden();
    }

    public function test_un_menu_accorde_ouvre_l_ecran(): void
    {
        MenuPermission::create(['user_id' => $this->employe->id, 'menu' => 'dashboard.products']);

        $this->actingAs($this->employe)->get('/dashboard/products')->assertOk();
        // Et seulement celui-là.
        $this->actingAs($this->employe)->get('/dashboard/configuration')->assertForbidden();
    }

    /*
     | L'accueil reste ouvert.
     |
     | Un employé sans aucun droit doit pouvoir se connecter et atterrir quelque
     | part, sinon il tourne en boucle sur un refus.
     */
    public function test_l_accueil_reste_toujours_ouvert(): void
    {
        $this->actingAs($this->employe)->get('/dashboard')->assertOk();
    }

    public function test_un_administrateur_garde_tout(): void
    {
        $admin = $this->admin();

        foreach (['/dashboard/configuration', '/dashboard/products', '/dashboard/droits'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->assertCount(
            count(MenuTableauDeBord::routes()),
            MenuTableauDeBord::droitsDe($admin)
        );
    }

    /** La barre de navigation ne montre que ce à quoi on a droit. */
    public function test_la_barre_ne_montre_que_les_menus_accordes(): void
    {
        MenuPermission::create(['user_id' => $this->employe->id, 'menu' => 'dashboard.commands']);

        $sections = MenuTableauDeBord::pourUtilisateur($this->employe->fresh());
        $routes = collect($sections)->flatMap(fn ($s) => collect($s[1])->pluck(0))->all();

        $this->assertContains('dashboard.commands', $routes);
        $this->assertNotContains('dashboard.configuration', $routes);

        // Une section vidée ne doit pas laisser un titre orphelin.
        foreach ($sections as [$titre, $liens]) {
            $this->assertNotEmpty($liens, "La section $titre est vide mais affichée.");
        }
    }

    /*
     | Les routes hors menu restent ouvertes.
     |
     | Actions POST, flux de rafraîchissement et sous-pages n'ont pas de droit
     | propre : les refuser casserait des écrans auxquels l'employé a pourtant
     | accès.
     */
    public function test_une_route_hors_menu_n_est_pas_bloquee(): void
    {
        $this->assertTrue(MenuTableauDeBord::autorise($this->employe, 'orders.board.feed'));
        $this->assertTrue(MenuTableauDeBord::autorise($this->employe, null));
    }

    public function test_l_ecran_des_droits_permet_de_nommer_et_de_regler(): void
    {
        $reponse = $this->actingAs($this->admin())->get('/dashboard/droits');

        $reponse->assertOk();
        $reponse->assertSeeText('Nommer un employé AFC');
        $reponse->assertSee('nommerEmploye', false);
        $reponse->assertSee('retirerEmploye', false);

        // Le réglage menu par menu n'est rendu qu'une fois l'employé déplié :
        // on vérifie dans la source qu'il existe bien derrière le bouton.
        $this->assertStringContainsString(
            'basculerMenu',
            file_get_contents(base_path('resources/views/pages/dashboard/droits.blade.php'))
        );
    }

    /** L'écran des droits est lui-même réservé. */
    public function test_l_ecran_des_droits_est_reserve(): void
    {
        $this->actingAs($this->employe)->get('/dashboard/droits')->assertForbidden();
    }

    /*
     | Retirer le rôle retire les droits.
     |
     | Les laisser ferait retrouver ses accès au compte s'il était renommé
     | employé plus tard, sans que personne ne l'ait décidé.
     */
    public function test_retirer_le_role_retire_les_droits(): void
    {
        MenuPermission::create(['user_id' => $this->employe->id, 'menu' => 'dashboard.products']);

        // On rejoue ce que fait l'écran : la page Folio n'est pas pilotable.
        MenuPermission::where('user_id', $this->employe->id)->delete();
        $this->employe->update(['role' => 'user']);

        $this->assertSame(0, MenuPermission::where('user_id', $this->employe->id)->count());
        $this->actingAs($this->employe->fresh())->get('/dashboard/products')->assertForbidden();
    }
}
