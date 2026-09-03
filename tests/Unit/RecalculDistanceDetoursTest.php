<?php

namespace Tests\Unit;

use App\Models\Clando;
use App\Models\ClandoStop;
use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use App\Support\RecalculDistanceDetours;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Détours d'une course : la distance vient d'OSRM (simulé ici), le prix de
 * base de Tarification — arrondi et majoration VIP compris, ce que l'ancien
 * calcul direct sur `parameters` oubliait. Aucun accès base : modèles non
 * persistés, grille doublée.
 */
class RecalculDistanceDetoursTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $parametres = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50]);
        $this->app->instance(GrilleTarifaire::class, new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    private function course(string $type = 'classic'): Clando
    {
        return new Clando([
            'ref' => 'TEST', 'type' => $type,
            'latMyPosition' => 9.30, 'lonMyPosition' => 13.39,
            'latDestination' => 9.32, 'lonDestination' => 13.40,
            'price' => 1050, 'base_price' => 1050, 'distance' => 4.2,
        ]);
    }

    public function test_les_points_de_passage_vont_du_depart_aux_detours_puis_a_la_destination(): void
    {
        $detours = [new ClandoStop(['lat' => 9.31, 'lon' => 13.395]), new ClandoStop(['lat' => 9.315, 'lon' => 13.398])];

        $this->assertSame(
            ['13.39,9.3', '13.395,9.31', '13.398,9.315', '13.4,9.32'],
            RecalculDistanceDetours::pointsDePassage($this->course(), $detours),
        );
    }

    public function test_osrm_donne_la_distance_en_kilometres(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 4200]]])]);

        $this->assertSame(4.2, RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32']));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/route/v1/driving/13.39,9.3;13.4,9.32'));
    }

    public function test_une_reponse_osrm_inattendue_ou_une_panne_donne_null(): void
    {
        Log::shouldReceive('warning')->twice();

        Http::fake(['router.project-osrm.org/*' => Http::response(['code' => 'NoRoute'])]);
        $this->assertNull(RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32'], 'REF'));

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertNull(RecalculDistanceDetours::distanceParOsrm(['13.39,9.3', '13.4,9.32'], 'REF'));
    }

    public function test_appliquer_recalcule_la_base_avec_arrondi_et_vip(): void
    {
        $classique = RecalculDistanceDetours::appliquer($this->course('classic'), 6.1);
        $this->assertSame(6.1, (float) $classique->distance);
        $this->assertSame(1550, (int) $classique->base_price); // 6.1×250=1525 → 1550

        $vip = RecalculDistanceDetours::appliquer($this->course('vip'), 6.1);
        $this->assertSame(2350, (int) $vip->base_price); // 1550 + 50 % = 2325 → 2350
    }
}
