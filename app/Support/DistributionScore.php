<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\order_detail;
use App\Models\Parameter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Score qui décide quel agent reçoit une course en premier.
 *
 * Seule classe de tout le projet qui connaît la formule 35/25/15/10/10/5
 * (ETA/distance/qualité/fiabilité/acceptation/priorité). Ni la note affichée
 * (NotationAgent) ni les indicateurs (IndicateursAgent) ne savent qu'ils
 * alimentent une décision d'attribution — c'est volontaire : un client ou un
 * agent ne doit jamais pouvoir remonter du chiffre qu'il voit au chiffre qui
 * a décidé de l'attribution.
 *
 * Consommée par AttributionAgent (dashboard, sur demande) et OffresDeCourse
 * (mobile, au moment où une course devient "want").
 */
class DistributionScore
{
    /**
     * Classe les candidats pour une course donnée, du meilleur au moins bon.
     *
     * @param  Clando|order_detail  $cible
     * @param  Collection<int, array{id_user:int, type:?string, lat:?float, lon:?float}>  $candidats
     * @return array<int, array{id_user:int, score:float, distance_km:?float, eta_min:?float, qualite:float, fiabilite:float, acceptation:float, priorite:float}>
     */
    public static function classerCandidats(Model $cible, Collection $candidats): array
    {
        if ($candidats->isEmpty()) {
            return [];
        }

        [$latCible, $lonCible] = self::pointDeDepart($cible);

        $indicateurs = IndicateursAgent::pourAgents($candidats->pluck('id_user'));

        $vitesse = self::vitesseKmh();

        $distances = [];
        $etas = [];

        foreach ($candidats as $candidat) {
            if ($latCible === null || $candidat['lat'] === null) {
                // Pas de coordonnées de départ exploitables (commande de type
                // coursier sans latShop/lonShop) : distance/ETA neutralisées
                // plutôt que devinées, voir pointDeDepart().
                $distances[$candidat['id_user']] = null;
                $etas[$candidat['id_user']] = null;

                continue;
            }

            $metres = Distance::metres($latCible, $lonCible, $candidat['lat'], $candidat['lon']);
            $distances[$candidat['id_user']] = $metres / 1000;
            $etas[$candidat['id_user']] = ($metres / 1000) / $vitesse * 60;
        }

        $distanceNormalisee = self::normaliserInverse($distances);
        $etaNormalisee = self::normaliserInverse($etas);

        $resultats = [];

        foreach ($candidats as $candidat) {
            $id = $candidat['id_user'];
            $ind = $indicateurs[$id] ?? ['qualite' => 60.0, 'fiabilite' => 100.0, 'acceptation' => 100.0];
            $priorite = ($candidat['type'] ?? 'classic') === 'vip' ? 100.0 : 50.0;

            $score = 0.35 * $etaNormalisee[$id]
                + 0.25 * $distanceNormalisee[$id]
                + 0.15 * $ind['qualite']
                + 0.10 * $ind['fiabilite']
                + 0.10 * $ind['acceptation']
                + 0.05 * $priorite;

            $resultats[] = [
                'id_user' => $id,
                'score' => round($score, 2),
                'distance_km' => $distances[$id] !== null ? round($distances[$id], 2) : null,
                'eta_min' => $etas[$id] !== null ? round($etas[$id], 1) : null,
                'qualite' => $ind['qualite'],
                'fiabilite' => $ind['fiabilite'],
                'acceptation' => $ind['acceptation'],
                'priorite' => $priorite,
            ];
        }

        usort($resultats, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $resultats;
    }

    /**
     * Roster des candidats éligibles pour une course : en service, position
     * fraîche (règle des 2 minutes déjà utilisée par ClandoBoardController/
     * OrderMapController), dans le rayon configuré.
     *
     * @return Collection<int, array{id_user:int, type:?string, lat:?float, lon:?float}>
     */
    public static function candidatsEligibles(Model $cible): Collection
    {
        [$latCible, $lonCible] = self::pointDeDepart($cible);
        $rayonKm = self::rayonKm();

        $agents = User::query()
            ->where('role', 'agent')
            ->where('in_activity', 1)
            ->whereNotNull('actual_lat_position_agent')
            ->whereNotNull('actual_lon_position_agent')
            ->get(['id', 'actual_lat_position_agent', 'actual_lon_position_agent', 'position_updated_at']);

        $types = Agent::whereIn('id_user', $agents->pluck('id'))->pluck('type', 'id_user');

        return $agents
            ->filter(fn ($u) => $u->position_updated_at !== null
                && $u->position_updated_at->greaterThan(now()->subMinutes(2)))
            ->filter(function ($u) use ($latCible, $lonCible, $rayonKm) {
                if ($latCible === null) {
                    return true;
                }

                $km = Distance::metres($latCible, $lonCible, (float) $u->actual_lat_position_agent, (float) $u->actual_lon_position_agent) / 1000;

                return $km <= $rayonKm;
            })
            ->map(fn ($u) => [
                'id_user' => (int) $u->id,
                'type' => $types[$u->id] ?? 'classic',
                'lat' => (float) $u->actual_lat_position_agent,
                'lon' => (float) $u->actual_lon_position_agent,
            ])
            ->values();
    }

    /**
     * Coordonnées du point de départ de la course/commande, ou [null, null]
     * si elles n'existent pas pour ce type (commande "coursier" à adresse
     * libre — voir notes.blade.php::idsDesCourses() pour ce même critère de
     * distinction). Pas de coordonnée devinée : le terme distance/ETA est
     * neutralisé pour tous les candidats plutôt que faussé pour certains.
     */
    private static function pointDeDepart(Model $cible): array
    {
        if ($cible instanceof Clando) {
            return [
                $cible->latMyPosition !== null ? (float) $cible->latMyPosition : null,
                $cible->lonMyPosition !== null ? (float) $cible->lonMyPosition : null,
            ];
        }

        if ($cible instanceof order_detail) {
            if ($cible->latShop !== null && $cible->lonShop !== null) {
                return [(float) $cible->latShop, (float) $cible->lonShop];
            }

            return [null, null];
        }

        return [null, null];
    }

    /**
     * Normalisation min-max au sein du pool de candidats, où la plus petite
     * valeur (le plus proche/rapide) obtient 100 et la plus grande 0 — pas de
     * seuil absolu, aucune ETA/distance n'est "bonne" ou "mauvaise" dans
     * l'absolu, cela dépend entièrement de la zone. Une valeur manquante
     * (coordonnées indisponibles) obtient le point médian 50, ni avantagée ni
     * pénalisée.
     *
     * @param  array<int, float|null>  $valeurs
     * @return array<int, float>
     */
    private static function normaliserInverse(array $valeurs): array
    {
        $connues = array_filter($valeurs, fn ($v) => $v !== null);

        if (empty($connues)) {
            return array_fill_keys(array_keys($valeurs), 50.0);
        }

        $min = min($connues);
        $max = max($connues);

        $resultat = [];

        foreach ($valeurs as $id => $v) {
            if ($v === null) {
                $resultat[$id] = 50.0;

                continue;
            }

            $resultat[$id] = $max - $min <= 0 ? 100.0 : round(100 * ($max - $v) / ($max - $min), 1);
        }

        return $resultat;
    }

    private static function vitesseKmh(): float
    {
        return (float) (Parameter::active()?->distribution_vitesse_kmh ?? 25.0);
    }

    private static function rayonKm(): float
    {
        return (float) (Parameter::active()?->distribution_rayon_km ?? 8.0);
    }
}
