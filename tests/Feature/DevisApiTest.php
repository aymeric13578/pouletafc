<?php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use Tests\TestCase;

/**
 * L'endpoint de devis, avec une GrilleTarifaire doublée dans le conteneur :
 * aucune table `tarifs` n'existe sur la base locale, et le test doit de toute
 * façon être insensible à la ligne `parameters` réellement active.
 */
class DevisApiTest extends TestCase
{
    private function avecGrille(?TarifPlage $plage, ?Parameter $parametres): void
    {
        $this->app->instance(GrilleTarifaire::class, new class($plage, $parametres) extends GrilleTarifaire {
            public function __construct(private ?TarifPlage $p, private ?Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return $this->p; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    private function parametres(): Parameter
    {
        return new Parameter([
            'clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50,
            'command_kilometer' => 63, 'min_price_command' => 400,
            'coursier_kilometer' => 200, 'min_price_coursier' => 500,
        ]);
    }

    public function test_un_devis_clando_vip_est_calcule_par_le_serveur(): void
    {
        $this->avecGrille(null, $this->parametres());

        $reponse = $this->postJson('/api/v2/devis', [
            'service' => 'clando', 'distance_km' => 4.2, 'type' => 'vip',
        ]);

        $reponse->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.prix', 1600)
            ->assertJsonPath('data.prix_classique', 1050)
            ->assertJsonPath('data.type', 'vip')
            ->assertJsonPath('data.source', 'parameters')
            ->assertJsonPath('data.devise', 'XAF');
    }

    public function test_get_et_post_donnent_la_meme_reponse(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->getJson('/api/v2/devis?service=coursier&distance_km=3')
            ->assertOk()->assertJsonPath('data.prix', 1100);
        $this->postJson('/api/v2/devis', ['service' => 'coursier', 'distance_km' => 3])
            ->assertOk()->assertJsonPath('data.prix', 1100);
    }

    public function test_la_grille_l_emporte_quand_elle_existe(): void
    {
        $this->avecGrille(new TarifPlage([
            'debut' => '00:00', 'fin' => '00:00', 'prix_km' => 300, 'prix_min' => 700,
            'prix_max' => null, 'commission' => 20, 'ordre' => 0,
        ]), $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'livraison', 'distance_km' => 10])
            ->assertOk()
            ->assertJsonPath('data.prix', 3000)
            ->assertJsonPath('data.source', 'grille')
            ->assertJsonPath('data.tarif.prix_km', 300);
    }

    public function test_service_inconnu_ou_distance_nulle_sont_refuses(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'colis', 'distance_km' => 3])
            ->assertStatus(422)->assertJsonValidationErrors(['service']);
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);
        $this->postJson('/api/v2/devis', ['service' => 'clando'])
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 2, 'type' => 'premium'])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_une_distance_plus_courte_que_le_vol_d_oiseau_est_refusee(): void
    {
        $this->avecGrille(null, $this->parametres());

        // Douala centre → Bonabéri ≈ 8,9 km à vol d'oiseau : 1 km de route est impossible.
        $coords = ['lat_depart' => 4.0511, 'lon_depart' => 9.7679, 'lat_arrivee' => 4.0725, 'lon_arrivee' => 9.6906];

        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 1] + $coords)
            ->assertStatus(422)->assertJsonValidationErrors(['distance_km']);

        // 9 km de route pour ≈ 8,9 km à vol d'oiseau : cohérent.
        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 9] + $coords)
            ->assertOk();
    }

    public function test_les_coordonnees_vont_par_quatre(): void
    {
        $this->avecGrille(null, $this->parametres());

        $this->postJson('/api/v2/devis', ['service' => 'clando', 'distance_km' => 2, 'lat_depart' => 4.05])
            ->assertStatus(422)->assertJsonValidationErrors(['lon_depart', 'lat_arrivee', 'lon_arrivee']);
    }
}
