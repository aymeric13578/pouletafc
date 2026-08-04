<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Tests\TestCase;

/**
 * Écran des boutiques, repris de l'ancien back-office Bootstrap sur les
 * composants du tableau de bord.
 */
class BoutiquesTest extends TestCase
{
    protected function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_l_ecran_s_affiche(): void
    {
        $this->actingAs($this->admin())->get('/dashboard/boutiques')->assertOk();
    }

    public function test_il_est_ferme_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();
        $this->actingAs($client)->get('/dashboard/boutiques')->assertForbidden();
    }

    public function test_il_montre_les_boutiques_et_leur_responsable(): void
    {
        $boutique = Shop::whereNotNull('id_user')->whereHas('user')->firstOrFail();

        $this->actingAs($this->admin())
            ->get('/dashboard/boutiques')
            ->assertSee($boutique->shop_name)
            ->assertSee($boutique->user->name);
    }

    public function test_le_lien_est_dans_la_navigation(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee('Boutiques');
    }

    public function test_le_comptage_des_produits_correspond(): void
    {
        $boutique = Shop::withCount('produits')->firstOrFail();

        $this->assertSame(
            \App\Models\Product::where('id_shop', $boutique->id)->count(),
            $boutique->produits_count
        );
    }

    public function test_le_modele_expose_les_bons_champs_remplissables(): void
    {
        /*
         * Le fillable déclarait "name", colonne inexistante, et omettait id_user
         * et status : toute écriture de masse sur ces champs était ignorée en
         * silence, donc impossible de rattacher un responsable.
         */
        $fillable = (new Shop())->getFillable();

        $this->assertContains('shop_name', $fillable);
        $this->assertContains('id_user', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertNotContains('name', $fillable);
    }
}
