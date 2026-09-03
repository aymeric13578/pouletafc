<?php

namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\TarifPlage;
use App\Support\Distance;
use App\Support\FraisDeLivraison;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Frais de livraison d'une commande : décidés par le serveur à partir de la
 * distance, jamais recopiés du téléphone. Fonction pure (doublure de la
 * grille) : la base locale n'a pas les tables nécessaires (51 migrations en
 * attente).
 */
class FraisDeLivraisonTest extends TestCase
{
    /** Point de retrait (Garoua) et point de livraison à ~3,7 km à vol d'oiseau. */
    private const RETRAIT = ['id' => 1, 'nom' => 'Comptoir', 'lat' => 9.2982, 'lon' => 13.3991];
    private const LIVRAISON = ['lat' => 9.3300, 'lon' => 13.3900];

    private function frais(): FraisDeLivraison
    {
        // 200 F/km, minimum 400 : assez cher pour que le plancher ne masque rien.
        $parametres = new Parameter(['command_kilometer' => 200, 'min_price_command' => 400]);

        return new FraisDeLivraison(new Tarification(new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        }));
    }

    public function test_un_retrait_au_comptoir_ne_coute_rien_quel_que_soit_le_reste(): void
    {
        foreach (['AFC', 'afc', 'retrait', 'Pickup', 'sur_place'] as $mode) {
            $r = $this->frais()->calculer('650', '10', $mode, self::RETRAIT, self::LIVRAISON);
            $this->assertSame(['frais' => 0, 'distance_km' => null, 'source' => 'retrait'], $r, $mode);
        }
    }

    public function test_avec_une_distance_le_serveur_calcule_et_ignore_les_frais_client(): void
    {
        Log::shouldReceive('warning')->once(); // prix client ≠ prix serveur (Tarification)

        $r = $this->frais()->calculer('100', '10', 'LIVRAISON', null, null);

        $this->assertSame(2000, $r['frais']);
        $this->assertSame(10.0, $r['distance_km']);
        $this->assertSame('client', $r['source']);
    }

    public function test_une_distance_coherente_avec_les_points_est_retenue_telle_quelle(): void
    {
        Log::shouldReceive('warning')->never();

        $r = $this->frais()->calculer(2000, 10, 'delivery', self::RETRAIT, self::LIVRAISON);

        $this->assertSame(2000, $r['frais']);
        $this->assertSame('client', $r['source']);
    }

    public function test_une_distance_plus_courte_que_le_vol_d_oiseau_est_remplacee_par_une_estimation(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();

        $volDOiseauKm = Distance::metres(self::RETRAIT['lat'], self::RETRAIT['lon'], self::LIVRAISON['lat'], self::LIVRAISON['lon']) / 1000;
        $attendu = Tarification::arrondi50($volDOiseauKm * FraisDeLivraison::FACTEUR_ROUTE * 200);

        $r = $this->frais()->calculer('200', '1', 'LIVRAISON', self::RETRAIT, self::LIVRAISON);

        $this->assertSame($attendu, $r['frais']);
        $this->assertEqualsWithDelta($volDOiseauKm * FraisDeLivraison::FACTEUR_ROUTE, $r['distance_km'], 0.001);
        $this->assertSame('estimation_serveur', $r['source']);
    }

    public function test_sans_distance_les_frais_client_sont_conserves_comme_avant(): void
    {
        Log::shouldReceive('warning')->never();

        $r = $this->frais()->calculer('800', null, 'LIVRAISON', self::RETRAIT, self::LIVRAISON);

        $this->assertSame(['frais' => 800, 'distance_km' => null, 'source' => 'legacy'], $r);
        $this->assertSame(0, $this->frais()->calculer('abc', '', 'LIVRAISON', null, null)['frais']);
        $this->assertSame(0, $this->frais()->calculer(null, 0, null, null, null)['frais']);
    }
}
