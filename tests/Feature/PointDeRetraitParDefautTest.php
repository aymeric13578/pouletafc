<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Parameter;
use App\Models\Quarter;
use App\Models\User;
use App\Support\PointDeLivraison;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Point de retrait quand le client ne choisit pas sa zone.
 *
 * Le point retombait sur users.latitude/longitude, la dernière position connue
 * du compte. Pour un compte jamais localisé, ces colonnes portent toutes la
 * même valeur : le point de retrait était donc identique pour tout le monde, et
 * parfois à des centaines de kilomètres de Garoua.
 */
class PointDeRetraitParDefautTest extends TestCase
{
    private ?int $idInitial = null;
    private ?Location $lieu = null;

    protected function setUp(): void
    {
        parent::setUp();

        $grille = Parameter::active();

        if (! $grille) {
            $this->markTestSkipped('Aucune configuration active.');
        }

        $this->idInitial = $grille->default_pickup_location_id;

        $quartier = Quarter::first() ?? Quarter::create([
            'name' => 'Quartier essai',
            'status' => 'Success',
        ]);

        // Un point bien réel à Garoua, pour que le test échoue si le contrôle
        // de zone se met à rejeter des coordonnées correctes.
        $this->lieu = Location::create([
            'id_quarter' => $quartier->id,
            'name' => 'Dépôt essai',
            'latitude' => '9.3017',
            'longitude' => '13.3921',
            'status' => 'Success',
        ]);
    }

    protected function tearDown(): void
    {
        Parameter::active()?->update(['default_pickup_location_id' => $this->idInitial]);
        $this->lieu?->delete();

        parent::tearDown();
    }

    private function resoudre(?User $client): array
    {
        return (new PointDeLivraison())->resoudre(new Request(), $client);
    }

    public function test_sans_lieu_designe_le_point_reste_indetermine(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => null]);

        [$lat, $lon, $origine] = $this->resoudre(null);

        $this->assertNull($lat);
        $this->assertNull($lon);
        $this->assertSame('aucune', $origine);
    }

    public function test_le_lieu_designe_sert_de_repli(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => $this->lieu->id]);

        [$lat, $lon, $origine] = $this->resoudre(null);

        $this->assertSame('lieu_par_defaut', $origine);
        $this->assertEqualsWithDelta(9.3017, $lat, 0.0001);
        $this->assertEqualsWithDelta(13.3921, $lon, 0.0001);
    }

    /*
     | La position du client garde la priorité.
     |
     | Quand elle existe, elle reste plus proche de la vérité qu'un point unique
     | valable pour toute la ville. Le lieu désigné n'est qu'un dernier recours.
     */
    public function test_la_position_du_client_passe_avant_le_repli(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => $this->lieu->id]);

        $client = new User(['latitude' => '9.35', 'longitude' => '13.42']);

        [$lat, $lon, $origine] = $this->resoudre($client);

        $this->assertSame('position_client', $origine);
        $this->assertEqualsWithDelta(9.35, $lat, 0.0001);
    }

    /*
     | Un lieu supprimé ne doit pas figer le point.
     |
     | La table des lieux est alimentée depuis les téléphones des agents ; un
     | lieu désigné peut disparaître. Sans ce contrôle, la résolution partirait
     | sur des coordonnées nulles au lieu de le dire.
     */
    public function test_un_lieu_supprime_ne_bloque_pas_la_resolution(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => $this->lieu->id]);
        $this->lieu->delete();
        $this->lieu = null;

        [$lat, $lon, $origine] = $this->resoudre(null);

        $this->assertNull($lat);
        $this->assertSame('aucune', $origine);
    }
}
