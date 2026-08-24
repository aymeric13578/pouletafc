<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Support\Facades\DB;

/**
 * Quatre indicateurs internes 0-100, séparés de la note affichée
 * (NotationAgent::noteAffichee) et jamais montrés comme "la note" à un
 * client — ce sont des mesures de qualité de service, pas l'appréciation
 * laissée par les clients.
 *
 * "Sécurité" n'existe pas ici : aucune donnée d'incident/signalement n'existe
 * nulle part dans ce projet, et inventer un chiffre serait pire que de ne pas
 * en afficher.
 *
 * Batché comme NotationAgent::pourAgents() : DistributionScore note un pool
 * entier de candidats à chaque course, une requête par indicateur pour tout
 * le pool plutôt qu'une par agent.
 */
class IndicateursAgent
{
    /** Fenêtre de calcul pour fiabilité/acceptation — pas un réglage exposé, personne n'a demandé à le régler. */
    private const FENETRE_JOURS = 90;

    /**
     * @param  iterable<int>  $idsAgents  id_user des agents
     * @return array<int, array{ponctualite: float, qualite: float, fiabilite: float, acceptation: float}>
     */
    public static function pourAgents(iterable $idsAgents): array
    {
        $ids = collect($idsAgents)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $qualite = self::qualitePourAgents($ids);
        $fiabilite = self::fiabilitePourAgents($ids);
        $acceptation = self::acceptationPourAgents($ids);
        $ponctualite = self::ponctualitePourAgents($ids);

        $resultat = [];

        foreach ($ids as $id) {
            $resultat[(int) $id] = [
                'ponctualite' => $ponctualite[(int) $id] ?? 70.0,
                'qualite' => $qualite[(int) $id] ?? 60.0,
                'fiabilite' => $fiabilite[(int) $id] ?? 100.0,
                'acceptation' => $acceptation[(int) $id] ?? 100.0,
            ];
        }

        return $resultat;
    }

    public static function pourAgent(int $idAgent): array
    {
        return self::pourAgents([$idAgent])[$idAgent];
    }

    /**
     * Qualité = note_affichee (1-5) ramenée sur 0-100. 60 par défaut (ni bon
     * ni mauvais, juste inconnu) pour un agent sans aucune note — en dessous
     * de ce que vaudrait la moyenne globale rescalée, pour qu'un agent sans
     * historique ne devance jamais le terrain rien qu'en n'ayant pas encore
     * été noté.
     */
    private static function qualitePourAgents($ids): array
    {
        $notes = NotationAgent::noteAfficheePourAgents($ids);
        $resultat = [];

        foreach ($notes as $id => $note) {
            $resultat[$id] = $note === null ? 60.0 : round(($note - 1) / 4 * 100, 1);
        }

        return $resultat;
    }

    /**
     * Fiabilité = 100 × (1 - annulé_par_agent / total_pris), sur les 90
     * derniers jours, toutes prestations confondues (clando + commande).
     * 100 par défaut si l'agent n'a encore rien pris : l'absence d'échec
     * n'est pas une preuve de fiabilité, mais ce n'est pas non plus une
     * preuve du contraire — un agent neuf ne doit pas démarrer en déficit
     * sur un indicateur qui n'existe que pour repérer les échecs.
     */
    private static function fiabilitePourAgents($ids): array
    {
        $depuis = now()->subDays(self::FENETRE_JOURS);
        $totaux = [];
        $annules = [];

        foreach (self::tablesPrestations() as [$table, $model]) {
            $lignes = DB::table($table)
                ->whereIn('id_agent', $ids)
                ->where('created_at', '>=', $depuis)
                ->selectRaw('id_agent, cancelled_by, COUNT(*) as nombre')
                ->groupBy('id_agent', 'cancelled_by')
                ->get();

            foreach ($lignes as $ligne) {
                $id = (int) $ligne->id_agent;
                $totaux[$id] = ($totaux[$id] ?? 0) + $ligne->nombre;

                if ($ligne->cancelled_by === 'agent') {
                    $annules[$id] = ($annules[$id] ?? 0) + $ligne->nombre;
                }
            }
        }

        $resultat = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            $total = $totaux[$id] ?? 0;

            if ($total === 0) {
                $resultat[$id] = 100.0;

                continue;
            }

            $resultat[$id] = round(100 * (1 - ($annules[$id] ?? 0) / $total), 1);
        }

        return $resultat;
    }

    /**
     * Acceptation = 100 × pris / (pris + déclinés), sur 90 jours.
     *
     * Approximation documentée : aucun journal d'"offre" n'existait avant
     * course_offer_waves (voir OffresDeCourse), donc on ne peut pas mesurer
     * "accepté / proposé" — seulement "pris / (pris + explicitement décliné
     * via declin_command)". Une fois course_offer_waves accumulée dans le
     * temps, un taux plus précis deviendra possible (hors de cette passe).
     */
    private static function acceptationPourAgents($ids): array
    {
        $depuis = now()->subDays(self::FENETRE_JOURS);
        $pris = [];

        foreach (self::tablesPrestations() as [$table, $model]) {
            DB::table($table)
                ->whereIn('id_agent', $ids)
                ->where('created_at', '>=', $depuis)
                ->selectRaw('id_agent, COUNT(*) as nombre')
                ->groupBy('id_agent')
                ->get()
                ->each(function ($ligne) use (&$pris) {
                    $id = (int) $ligne->id_agent;
                    $pris[$id] = ($pris[$id] ?? 0) + $ligne->nombre;
                });
        }

        $declines = DB::table('declin_command')
            ->whereIn('id_user', $ids)
            ->where('created_at', '>=', $depuis)
            ->selectRaw('id_user, COUNT(*) as nombre')
            ->groupBy('id_user')
            ->pluck('nombre', 'id_user');

        $resultat = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            $p = $pris[$id] ?? 0;
            $d = (int) ($declines[$id] ?? 0);
            $denominateur = $p + $d;

            $resultat[$id] = $denominateur === 0 ? 100.0 : round(100 * $p / $denominateur, 1);
        }

        return $resultat;
    }

    /**
     * Ponctualité = rapidité de réponse à une offre de course — pas
     * "ponctualité à l'arrivée", pour laquelle aucune donnée d'ETA promise
     * n'existe nulle part.
     *
     * Mesuré uniquement sur les refus explicites : course_offer_waves.visible_at
     * (l'offre) jusqu'à declin_command.created_at (la décision), le seul
     * couple de repères tous deux non ambigus. Une prise n'a pas d'horodatage
     * dédié distinct de la dernière mise à jour de la course (qui bouge à
     * chaque étape suivante du trajet, pas seulement à la prise) — plutôt que
     * d'en déduire un délai approximatif et potentiellement faux, ces cas
     * sont simplement ignorés pour ce calcul.
     *
     * ≤10s → 100, ≥120s → 0, linéaire entre les deux. 70 par défaut tant
     * qu'aucun refus mesurable n'existe (la table est neuve au lancement) :
     * ni le plancher "jamais répondu" ni le plafond "toujours instantané" ne
     * seraient honnêtes sans données.
     */
    private static function ponctualitePourAgents($ids): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('course_offer_waves')) {
            return [];
        }

        $depuis = now()->subDays(self::FENETRE_JOURS);

        $offres = DB::table('course_offer_waves')
            ->whereIn('id_user', $ids)
            ->where('created_at', '>=', $depuis)
            ->select('id_user', 'id_clando', 'id_order', 'visible_at')
            ->get();

        $declines = DB::table('declin_command')
            ->whereIn('id_user', $ids)
            ->where('created_at', '>=', $depuis)
            ->select('id_user', 'id_clando', 'id_order', 'created_at')
            ->get();

        $delaisParAgent = [];

        foreach ($offres as $offre) {
            $decline = $declines->first(fn ($d) => $d->id_user === $offre->id_user
                && $d->id_clando === $offre->id_clando
                && $d->id_order === $offre->id_order);

            if (! $decline) {
                continue;
            }

            $delai = \Carbon\Carbon::parse($offre->visible_at)->diffInSeconds(\Carbon\Carbon::parse($decline->created_at), false);

            if ($delai < 0) {
                continue;
            }

            $delaisParAgent[$offre->id_user][] = $delai;
        }

        $resultat = [];

        foreach ($delaisParAgent as $idUser => $delais) {
            sort($delais);
            $mediane = $delais[(int) floor(count($delais) / 2)];

            $resultat[(int) $idUser] = round(max(0, min(100, 100 - ($mediane - 10) / 110 * 100)), 1);
        }

        return $resultat;
    }

    /** @return array<int, array{0: string, 1: string}> [nom_table, classe_modele] */
    private static function tablesPrestations(): array
    {
        return [
            ['order_details', order_detail::class],
            ['clando', Clando::class],
        ];
    }
}
