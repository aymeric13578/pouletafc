<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Tests\TestCase;

/**
 * Après connexion, tout le monde atterrissait sur la boutique publique : un
 * marchand rattaché à une boutique devait deviner l'URL de son espace.
 */
class RedirectionConnexionTest extends TestCase
{
    public function test_un_marchand_arrive_dans_son_espace_boutique(): void
    {
        $boutique = Shop::whereNotNull('id_user')
            ->whereHas('user', fn ($q) => $q->whereNotIn('role', ['admin', 'employee_afc']))
            ->first();

        if (! $boutique) {
            $this->markTestSkipped('Aucune boutique rattachée à un compte non interne.');
        }

        $this->actingAs(User::findOrFail($boutique->id_user))
            ->get('/dashboard')
            ->assertForbidden();

        $this->assertTrue(
            Shop::where('id_user', $boutique->id_user)->exists(),
            'Le rattachement boutique -> utilisateur doit exister pour piloter la redirection.'
        );
    }

    public function test_l_equipe_interne_passe_avant_le_rattachement_boutique(): void
    {
        /*
         * Un employé peut posséder une boutique (« Poulet AFC » appartient à un
         * compte employee_afc) : il doit arriver sur le tableau de bord, pas dans
         * l'espace marchand.
         */
        $employe = User::where('role', 'employee_afc')
            ->whereIn('id', Shop::whereNotNull('id_user')->pluck('id_user'))
            ->first();

        if (! $employe) {
            $this->markTestSkipped('Aucun employé interne ne possède de boutique.');
        }

        $this->actingAs($employe)->get('/dashboard')->assertOk();
    }
}
