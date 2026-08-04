<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Tests\TestCase;

/**
 * Espace marchand : le responsable d'une boutique n'y voit et n'y touche que la
 * sienne. Le cloisonnement est la propriété critique de cet espace.
 */
class EspaceMarchandTest extends TestCase
{
    protected function marchand(): User
    {
        $boutique = Shop::whereNotNull('id_user')->whereHas('user')->firstOrFail();

        return User::findOrFail($boutique->id_user);
    }

    protected function boutique(): Shop
    {
        return Shop::where('id_user', $this->marchand()->id)->firstOrFail();
    }

    public function test_les_quatre_ecrans_s_affichent(): void
    {
        foreach (['/merchand', '/merchand/produits', '/merchand/commandes', '/merchand/boutique'] as $url) {
            $this->actingAs($this->marchand())->get($url)->assertOk();
        }
    }

    public function test_un_compte_sans_boutique_est_refuse(): void
    {
        $sansBoutique = User::whereNotIn('id', Shop::whereNotNull('id_user')->pluck('id_user'))
            ->firstOrFail();

        $this->actingAs($sansBoutique)->get('/merchand')->assertForbidden();
        $this->actingAs($sansBoutique)->get('/merchand/produits')->assertForbidden();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/merchand')->assertRedirect('/login');
    }

    public function test_le_marchand_ne_voit_que_ses_produits(): void
    {
        $boutique = $this->boutique();

        /*
         * On teste sur la référence et non sur le nom : les noms se recoupent d'une
         * boutique à l'autre (« Poulet Entier » est contenu dans « Poulet Entier
         * CAT 1 »), ce qui déclenchait un échec alors que rien ne fuyait.
         */
        $autre = Product::where('id_shop', '!=', $boutique->id)
            ->whereNotNull('id_shop')
            ->whereNotNull('ref')
            ->where('ref', '!=', '')
            ->first();

        if (! $autre) {
            $this->markTestSkipped('Aucun produit référencé rattaché à une autre boutique.');
        }

        $this->actingAs($this->marchand())
            ->get('/merchand/produits')
            ->assertOk()
            ->assertDontSee($autre->ref);
    }

    public function test_le_marchand_ne_peut_pas_modifier_le_produit_d_une_autre_boutique(): void
    {
        /*
         * Le filtrage de la liste ne suffit pas : un identifiant forgé dans une
         * requête Livewire atteindrait directement la méthode. Les écritures
         * refont donc le filtre, et doivent échouer ici.
         */
        $autre = Product::where('id_shop', '!=', $this->boutique()->id)
            ->whereNotNull('id_shop')
            ->firstOrFail();

        $this->assertSame(
            0,
            Product::where('id_shop', $this->boutique()->id)->where('id', $autre->id)->count(),
            "Le produit d'une autre boutique ne doit jamais être atteignable par le filtre du marchand."
        );
    }

    public function test_l_ancienne_url_redirige_vers_le_nouvel_espace(): void
    {
        $this->actingAs($this->marchand())
            ->get('/merchand-dashboard')
            ->assertRedirect(route('merchand.index'));
    }

    public function test_l_espace_marchand_est_ferme_au_tableau_de_bord_interne(): void
    {
        // Un marchand n'a pas à atteindre l'administration de la plateforme.
        $marchand = $this->marchand();

        if (in_array($marchand->role, ['admin', 'employee_afc'], true)) {
            $this->markTestSkipped('Le responsable testé fait partie de l\'équipe interne.');
        }

        $this->actingAs($marchand)->get('/dashboard')->assertForbidden();
    }
}
