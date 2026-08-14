<?php

namespace Tests\Feature;

use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Recherche et détails sur le mur des commandes.
 *
 * Le mur n'affiche que douze commandes par page : retrouver celle qu'un client
 * cite au téléphone obligeait à parcourir la pagination à la main. Et la fiche
 * d'une course de coursier ne disait ni ce qu'il y avait à enlever, ni à qui le
 * remettre.
 */
class RechercheMurTest extends TestCase
{
    public function test_la_recherche_porte_sur_toute_la_base_et_non_sur_la_page(): void
    {
        /*
         * Le point essentiel : chercher dans les douze commandes affichées
         * n'aurait servi à rien. Une commande ancienne, invisible sans
         * pagination, doit remonter.
         */
        $ancienne = order_detail::orderBy('id')->firstOrFail();

        $data = $this->getJson('/commandes/flux?recherche=' . urlencode($ancienne->ref))
            ->assertOk()
            ->json();

        $this->assertContains($ancienne->ref, collect($data['orders'])->pluck('ref')->all());
        $this->assertSame($ancienne->ref, $data['recherche']);
    }

    public function test_la_recherche_trouve_par_nom_de_client(): void
    {
        $commande = order_detail::whereHas('user')->with('user')->orderByDesc('id')->first();

        if (! $commande?->user?->name) {
            $this->markTestSkipped('Aucune commande rattachée à un client nommé.');
        }

        $refs = collect(
            $this->getJson('/commandes/flux?recherche=' . urlencode($commande->user->name))
                ->assertOk()
                ->json('orders')
        )->pluck('ref');

        $this->assertContains($commande->ref, $refs->all());
    }

    public function test_une_recherche_sans_resultat_renvoie_une_liste_vide(): void
    {
        // Et non la liste complète : un filtre qui ne filtre pas trompe plus
        // qu'il n'aide.
        $data = $this->getJson('/commandes/flux?recherche=ZZZINTROUVABLEZZZ')->assertOk()->json();

        $this->assertSame([], $data['orders']);
        $this->assertSame(0, $data['pagination']['total']);
    }

    public function test_sans_recherche_le_mur_reste_complet(): void
    {
        $data = $this->getJson('/commandes/flux')->assertOk()->json();

        $this->assertNotEmpty($data['orders']);
        $this->assertSame('', $data['recherche']);
    }

    public function test_la_fiche_coursier_porte_de_quoi_travailler(): void
    {
        /*
         * Code de remise, contact, nature du colis : sans eux, le comptoir ne
         * peut ni dire à qui remettre, ni dépanner un client qui a perdu son
         * code.
         */
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $commande->only(['status', 'delivery_code', 'phone_customer', 'delivery_type']);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'delivery_code' => '7412',
            'phone_customer' => 690123456,
            'delivery_type' => 'Petit colis',
        ]);

        $trouvee = collect(
            $this->getJson('/commandes/flux?recherche=' . urlencode($commande->ref))->assertOk()->json('orders')
        )->firstWhere('ref', $commande->ref);

        $this->assertNotNull($trouvee);
        $this->assertSame('7412', $trouvee['delivery_code']);
        $this->assertSame('Petit colis', $trouvee['delivery_type']);
        $this->assertNotNull($trouvee['phone_customer']);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }
}
