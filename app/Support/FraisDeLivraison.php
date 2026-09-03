<?php

namespace App\Support;

use App\Models\Tarif;
use Illuminate\Support\Facades\Log;

/**
 * Frais de livraison d'une commande de panier, décidés par le serveur.
 *
 * Jusqu'ici `delivery_fees` était recopié tel quel depuis le téléphone —
 * calculé par l'application à partir d'une position et d'un point de
 * retrait codé en dur. Ici : la distance envoyée par l'application est
 * acceptée si elle n'est pas plus courte que le vol d'oiseau entre le point
 * de retrait et le point de livraison résolu (PointDeLivraison) ; sinon une
 * estimation routière la remplace. Le montant vient toujours de Tarification.
 *
 * Sans distance (ancien build), les frais client sont conservés : le total
 * enregistré doit rester celui que le client a vu à l'écran.
 */
class FraisDeLivraison
{
    /** Une route dépasse rarement le vol d'oiseau de plus d'un quart, en ville. */
    public const FACTEUR_ROUTE = 1.25;

    /** Même marge que DevisController pour les arrondis GPS. */
    public const TOLERANCE_VOL_D_OISEAU = 0.95;

    /** Valeurs de `reception_mode` qui signifient « le client vient chercher ». */
    public const MODES_RETRAIT = ['afc', 'retrait', 'pickup', 'sur_place'];

    public function __construct(private Tarification $tarification)
    {
    }

    /**
     * @param  array{lat: float, lon: float}|null  $pointDeRetrait
     * @param  array{lat: float, lon: float}|null  $pointDeLivraison
     * @return array{frais: int, distance_km: float|null, source: string}
     */
    public function calculer(
        mixed $fraisClient,
        mixed $distanceClientKm,
        ?string $receptionMode,
        ?array $pointDeRetrait,
        ?array $pointDeLivraison,
    ): array {
        if (in_array(mb_strtolower(trim((string) $receptionMode)), self::MODES_RETRAIT, true)) {
            return ['frais' => 0, 'distance_km' => null, 'source' => 'retrait'];
        }

        if (! is_numeric($distanceClientKm) || (float) $distanceClientKm <= 0) {
            return [
                'frais' => $this->tarification->prixRetenu(Tarif::LIVRAISON, $fraisClient, null) ?? 0,
                'distance_km' => null,
                'source' => 'legacy',
            ];
        }

        $distance = (float) $distanceClientKm;
        $source = 'client';

        $volDOiseau = $this->volDOiseauKm($pointDeRetrait, $pointDeLivraison);

        if ($volDOiseau !== null && $distance < $volDOiseau * self::TOLERANCE_VOL_D_OISEAU) {
            $estimation = $volDOiseau * self::FACTEUR_ROUTE;
            Log::warning('FraisDeLivraison: distance client incohérente, estimation serveur retenue', [
                'distance_client_km' => $distance,
                'vol_d_oiseau_km' => $volDOiseau,
                'estimation_km' => $estimation,
            ]);
            $distance = $estimation;
            $source = 'estimation_serveur';
        }

        return [
            'frais' => $this->tarification->prixRetenu(Tarif::LIVRAISON, $fraisClient, $distance) ?? 0,
            'distance_km' => $distance,
            'source' => $source,
        ];
    }

    private function volDOiseauKm(?array $a, ?array $b): ?float
    {
        if (! isset($a['lat'], $a['lon'], $b['lat'], $b['lon'])) {
            return null;
        }

        return Distance::metres((float) $a['lat'], (float) $a['lon'], (float) $b['lat'], (float) $b['lon']) / 1000;
    }
}
