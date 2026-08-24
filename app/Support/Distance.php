<?php

namespace App\Support;

/**
 * Distance à vol d'oiseau entre deux points GPS.
 *
 * Seule implémentation de la formule de Haversine dans ce projet — extraite de
 * AnnulationDeCommande (où elle ne servait qu'à vérifier la proximité d'un
 * agent déjà assigné) pour être réutilisée par DistributionScore, qui doit
 * comparer une course à un pool de plusieurs agents candidats.
 */
class Distance
{
    public static function metres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $rayonTerre = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $rayonTerre * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
