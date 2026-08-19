<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Location;
use App\Models\Quarter;
use App\Models\User;
use App\Models\order_detail;
use App\Support\LieuDeLivraison;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corriger le lieu d'une livraison sans annuler la commande.
 *
 * L'adresse était figée à la création : un quartier mal compris au téléphone
 * obligeait à annuler et à ressaisir, en perdant l'historique et l'agent déjà
 * attribué.
 */
class ChangementDuLieuTest extends TestCase
{
    private array $commandes = [];

    private array $lieuxCrees = [];

    private array $quartiersCrees = [];

    protected function tearDown(): void
    {
        if ($this->commandes) {
            DB::table('order_details')->whereIn('id', $this->commandes)->delete();
        }

        if ($this->lieuxCrees) {
            DB::table('locations')->whereIn('id', $this->lieuxCrees)->delete();
        }

        if ($this->quartiersCrees) {
            DB::table('quarters')->whereIn('id', $this->quartiersCrees)->delete();
        }

        parent::tearDown();
    }

    private function commande(): order_detail
    {
        $client = User::first();

        if (! $client) {
            $this->markTestSkipped('Base sans client.');
        }

        $commande = order_detail::create([
            'id_user' => $client->id,
            'ref' => 'TEST_' . uniqid(),
            'price' => 2000,
            'status' => 'pending',
            'address' => 'Adresse à corriger',
            'latitude' => 1.0,
            'longitude' => 2.0,
        ]);

        $this->commandes[] = $commande->id;

        return $commande;
    }

    public function test_un_lieu_enregistre_remplace_l_adresse_et_les_coordonnees(): void
    {
        $lieu = Location::whereNotNull('latitude')->whereNotNull('longitude')
            ->where('latitude', '!=', '')->first();

        if (! $lieu) {
            $this->markTestSkipped('Aucun lieu localisé en base.');
        }

        $commande = $this->commande();

        $this->postJson("/commandes/{$commande->id}/lieu", ['location_id' => $lieu->id])
            ->assertOk()
            ->assertJsonPath('lieu.ok', true);

        $commande->refresh();

        $this->assertStringContainsString($lieu->name, (string) $commande->address);

        /*
        | Les coordonnées suivent le libellé. Ne changer que le nom enverrait le
        | livreur à l'ancien point avec la nouvelle adresse sous les yeux — la
        | panne exacte qu'on venait de corriger ailleurs.
        */
        $this->assertEqualsWithDelta((float) $lieu->latitude, (float) $commande->latitude, 0.0001);
        $this->assertEqualsWithDelta((float) $lieu->longitude, (float) $commande->longitude, 0.0001);
    }

    public function test_un_lieu_inconnu_peut_etre_cree_depuis_la_commande(): void
    {
        $commande = $this->commande();
        $nom = 'Essai carrefour ' . uniqid();
        $quartier = 'Essai quartier ' . uniqid();

        $this->postJson("/commandes/{$commande->id}/lieu", [
            'name' => $nom,
            'quarter_name' => $quartier,
        ])->assertOk()->assertJsonPath('lieu.ok', true);

        $cree = Location::where('name', $nom)->first();
        $this->assertNotNull($cree, 'Le lieu doit rejoindre la liste commune.');
        $this->lieuxCrees[] = $cree->id;

        $q = Quarter::where('name', $quartier)->first();
        $this->assertNotNull($q, "Le quartier est créé s'il n'existe pas encore.");
        $this->quartiersCrees[] = $q->id;

        $commande->refresh();
        $this->assertStringContainsString($nom, (string) $commande->address);
    }

    public function test_un_lieu_deja_connu_n_est_pas_duplique(): void
    {
        $commande = $this->commande();
        $service = app(LieuDeLivraison::class);

        $nom = 'Essai unique ' . uniqid();

        $premier = $service->creer(['name' => $nom, 'quarter_name' => 'Essai Q ' . uniqid()], null);
        $this->lieuxCrees[] = $premier->id;
        if ($premier->id_quarter) $this->quartiersCrees[] = $premier->id_quarter;

        // Le même nom dans le même quartier : on réutilise, on ne peuple pas la
        // liste de doublons — ce qui est exactement arrivé côté agents.
        $second = $service->creer(['name' => $nom, 'quarter_id' => $premier->id_quarter], null);

        $this->assertSame($premier->id, $second->id);
    }

    public function test_ni_lieu_choisi_ni_lieu_nomme_est_refuse(): void
    {
        $commande = $this->commande();

        $this->postJson("/commandes/{$commande->id}/lieu", [])
            ->assertStatus(422)
            ->assertJsonPath('lieu.ok', false);

        $commande->refresh();
        $this->assertSame('Adresse à corriger', $commande->address);
    }

    public function test_les_ecrans_recoivent_la_liste_des_lieux(): void
    {
        $lieux = $this->getJson('/commandes/flux')->json('lieux');

        $this->assertNotEmpty($lieux);
        $this->assertArrayHasKey('libelle', $lieux[0]);
        $this->assertArrayHasKey('localise', $lieux[0]);
    }
}
