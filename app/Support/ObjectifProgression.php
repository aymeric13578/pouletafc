<?php

namespace App\Support;

use App\Models\Clando;
use App\Models\GoalCampaign;
use App\Models\GoalEnrollment;
use App\Models\GoalProgress;
use App\Models\order_detail;
use Illuminate\Support\Carbon;

/**
 * Calcul de la progression d'un agent sur une campagne d'objectifs.
 *
 * Utilisé à la fois par GoalController (application agent, à chaque
 * ouverture de l'écran) et par le tableau de bord (à la clôture d'une
 * campagne, pour figer le résultat). Les deux DOIVENT passer par ce même
 * calcul — c'est exactement le genre de logique dupliquée qui a déjà causé
 * des divergences ailleurs dans ce projet (voir la règle 5 de CLAUDE.md sur
 * le solde agent, calculé deux fois différemment).
 */
class ObjectifProgression
{
    /**
     * Recalculée depuis les courses réelles, jamais incrémentée — une course
     * annulée après coup doit pouvoir faire baisser la progression.
     */
    public static function calculer(GoalCampaign $campaign, $agentId, ?int $optionId = null): array
    {
        $debut = $campaign->starts_at;
        $fin = $campaign->ends_at;

        $refsClando = collect();
        $refsLivraisonEtCourse = collect();

        if ($campaign->ride_kind === null || $campaign->ride_kind === 'clando') {
            $refsClando = Clando::where('id_agent', $agentId)
                ->where('status', 'Success')
                ->whereBetween('created_at', [$debut, $fin])
                ->pluck('created_at', 'ref');
        }

        if ($campaign->ride_kind !== 'clando') {
            $query = order_detail::where('id_agent', $agentId)
                ->where('status', 'Success')
                ->whereBetween('created_at', [$debut, $fin]);

            if ($campaign->ride_kind === 'delivery') {
                $query->where('delivery_type', '!=', 'coursier');
            } elseif ($campaign->ride_kind === 'courier') {
                $query->where('delivery_type', 'coursier');
            }

            $refsLivraisonEtCourse = $query->pluck('created_at', 'ref');
        }

        $toutesLesDates = $refsClando->merge($refsLivraisonEtCourse);

        $progress = match ($campaign->metric) {
            'rides' => $toutesLesDates->count(),
            'active_days' => $toutesLesDates->map(fn ($d) => Carbon::parse($d)->toDateString())->unique()->count(),
            // Aucune colonne de distance n'existe sur les courses de cette
            // app : on ne fabrique pas de valeur.
            default => 0,
        };

        $option = $optionId ? $campaign->options()->find($optionId) : null;
        $target = $option?->threshold ?? 0;
        $achieved = $target > 0 && $progress >= $target;

        return [
            'progress' => $progress,
            'target' => $target,
            'achieved' => $achieved,
            'reward' => $option?->reward ?? 0,
            'a_contribue' => $toutesLesDates->isNotEmpty(),
        ];
    }

    /**
     * Calcule et enregistre la progression d'un agent (goal_progress), et
     * verrouille son engagement dès sa première course comptée.
     */
    public static function calculerEtEnregistrer(GoalCampaign $campaign, $agentId, int $optionId): array
    {
        $resultat = self::calculer($campaign, $agentId, $optionId);

        $ligne = GoalProgress::firstOrNew([
            'campaign_id' => $campaign->id,
            'agent_id' => $agentId,
        ]);
        $ligne->progress = $resultat['progress'];
        if ($resultat['achieved'] && ! $ligne->achieved_at) {
            $ligne->achieved_at = Carbon::now();
        } elseif (! $resultat['achieved']) {
            $ligne->achieved_at = null;
        }
        $ligne->save();

        if ($resultat['a_contribue']) {
            $enrollment = GoalEnrollment::where('campaign_id', $campaign->id)
                ->where('agent_id', $agentId)
                ->first();
            if ($enrollment && $enrollment->locked_at === null) {
                $enrollment->update(['locked_at' => Carbon::now()]);
            }
        }

        return $resultat + ['achieved_at' => $ligne->achieved_at];
    }

    /**
     * Fige la progression de tous les agents engagés sur une campagne — à
     * appeler à la clôture. Après ça, goal_progress.frozen_progress et
     * .amount_due ne bougent plus, même si de nouvelles courses arrivent.
     */
    public static function figerALaCloture(GoalCampaign $campaign): void
    {
        $enrollments = GoalEnrollment::where('campaign_id', $campaign->id)->get();

        foreach ($enrollments as $enrollment) {
            $resultat = self::calculerEtEnregistrer($campaign, $enrollment->agent_id, $enrollment->option_id);

            GoalProgress::where('campaign_id', $campaign->id)
                ->where('agent_id', $enrollment->agent_id)
                ->update([
                    'frozen_progress' => $resultat['progress'],
                    'amount_due' => $resultat['achieved'] ? $resultat['reward'] : 0,
                ]);
        }
    }
}
