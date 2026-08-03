<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Quarter;
use App\Models\User;
use Tests\TestCase;

/**
 * Écran des lieux et quartiers enregistrés par les agents depuis l'application
 * mobile. Ces données alimentent la recherche d'adresse de livraison du panier,
 * mais n'avaient aucun écran de consultation ni de correction.
 */
class LieuxTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_l_ecran_s_affiche(): void
    {
        $this->actingAs($this->admin())->get('/dashboard/lieux')->assertOk();
    }

    public function test_il_est_ferme_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/dashboard/lieux')->assertForbidden();
    }

    public function test_l_ecran_montre_le_nom_de_qui_a_enregistre_le_lieu(): void
    {
        $lieu = Location::whereNotNull('id_user')
            ->whereHas('user')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($this->admin())
            ->get('/dashboard/lieux')
            ->assertSee($lieu->user->name);
    }

    public function test_le_lien_est_dans_la_navigation(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertSee('Lieux');
    }

    public function test_un_quartier_porte_plusieurs_lieux(): void
    {
        $quartier = Quarter::withCount('locations')
            ->having('locations_count', '>', 1)
            ->first();

        if (! $quartier) {
            $this->markTestSkipped('Aucun quartier ne compte plusieurs lieux dans cette base.');
        }

        $this->assertGreaterThan(1, $quartier->locations()->count());
        $this->assertSame($quartier->id, $quartier->locations()->first()->id_quarter);
    }

    public function test_les_lieux_sans_coordonnees_sont_signales(): void
    {
        /*
         * Un lieu sans latitude ni longitude reste proposé au client dans le panier,
         * mais la livraison ne peut pas être calculée : l'écran doit les signaler
         * plutôt que de les noyer dans la liste.
         */
        $this->actingAs($this->admin())
            ->get('/dashboard/lieux')
            ->assertOk()
            ->assertSee('Sans coordonnées');
    }
}
