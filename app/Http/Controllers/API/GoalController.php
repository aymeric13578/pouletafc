<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use App\Models\GoalCampaign;
use App\Models\GoalEnrollment;
use App\Models\GoalProgress;
use App\Models\order_detail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Objectifs et primes — application agent.
 *
 * Un agent ne peut s'engager que sur une seule option par campagne
 * (contrainte unique campaign_id+agent_id sur goal_enrollments), et
 * l'engagement se verrouille dès la première course comptabilisée
 * (locked_at) : voir le document de conception du 2026-08-23. La création
 * et la clôture des campagnes n'ont pas d'écran dashboard pour l'instant —
 * ce contrôleur ne couvre que la lecture et l'engagement côté agent.
 *
 * Trois types de courses existent réellement dans cette app (clando,
 * livraison boutique, coursier), alors que le document d'origine ne
 * distinguait que « delivery »/« courier ». ride_kind accepte donc en plus
 * la valeur 'clando' ; null = les trois confondues.
 */
class GoalController extends Controller
{
    public function getGoalCampaigns(Request $request): JsonResponse
    {
        $request->validate(['id_agent' => ['required']]);
        $agentId = $request->input('id_agent');

        $campaigns = GoalCampaign::whereIn('status', ['running', 'closed'])
            ->with('options')
            ->orderByDesc('starts_at')
            ->limit(30)
            ->get();

        $enrollments = GoalEnrollment::whereIn('campaign_id', $campaigns->pluck('id'))
            ->where('agent_id', $agentId)
            ->get()
            ->keyBy('campaign_id');

        $data = $campaigns->map(function (GoalCampaign $campaign) use ($agentId, $enrollments) {
            $enrollment = $enrollments->get($campaign->id);
            $progress = null;

            if ($enrollment) {
                $progress = $this->calculerEtEnregistrerProgression($campaign, $agentId, (int) $enrollment->option_id);
            }

            return [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'metric' => $campaign->metric,
                'ride_kind' => $campaign->ride_kind,
                'starts_at' => $campaign->starts_at,
                'ends_at' => $campaign->ends_at,
                'enrollment_closes_at' => $campaign->enrollment_closes_at,
                'status' => $campaign->status,
                'options' => $campaign->options->map(fn ($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'threshold' => $o->threshold,
                    'reward' => $o->reward,
                ]),
                'enrollment' => $enrollment ? [
                    'option_id' => $enrollment->option_id,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'locked_at' => $enrollment->locked_at,
                    'auto_assigned' => $enrollment->auto_assigned,
                ] : null,
                'progress' => $progress,
            ];
        });

        return response()->json(['response' => 200, 'data' => $data]);
    }

    public function enrollGoalCampaign(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_agent' => ['required'],
            'id_campaign' => ['required', 'integer'],
            'id_option' => ['required', 'integer'],
        ]);

        $campaign = GoalCampaign::find($valide['id_campaign']);
        if (! $campaign || $campaign->status !== 'running') {
            return response()->json(['response' => 404, 'message' => "Cette campagne n'est plus disponible."], 404);
        }

        if (Carbon::now()->greaterThan($campaign->enrollment_closes_at)) {
            return response()->json(['response' => 409, 'message' => "L'engagement n'est plus possible : la fenêtre d'inscription est fermée."], 409);
        }

        $option = $campaign->options()->find($valide['id_option']);
        if (! $option) {
            return response()->json(['response' => 404, 'message' => "Cet objectif n'existe pas."], 404);
        }

        $existant = GoalEnrollment::where('campaign_id', $campaign->id)
            ->where('agent_id', $valide['id_agent'])
            ->first();

        if ($existant && $existant->locked_at !== null) {
            return response()->json(['response' => 409, 'message' => 'Votre engagement est verrouillé : la première course comptée ne permet plus de changer d\'objectif.'], 409);
        }

        if ($existant) {
            $existant->update(['option_id' => $option->id]);
        } else {
            GoalEnrollment::create([
                'campaign_id' => $campaign->id,
                'agent_id' => $valide['id_agent'],
                'option_id' => $option->id,
                'enrolled_at' => Carbon::now(),
                'auto_assigned' => false,
            ]);
        }

        return response()->json(['response' => 200, 'message' => 'Engagement enregistré.']);
    }

    /**
     * Recalculée à chaque appel depuis les courses réelles, jamais
     * incrémentée — une course annulée après coup doit pouvoir faire
     * baisser la progression, pas seulement monter.
     */
    private function calculerEtEnregistrerProgression(GoalCampaign $campaign, $agentId, int $optionId): array
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
            // app : on ne fabrique pas de valeur, la progression reste à 0
            // plutôt que d'afficher un chiffre inventé.
            default => 0,
        };

        $option = $campaign->options()->find($optionId);
        $target = $option?->threshold ?? 0;
        $achieved = $target > 0 && $progress >= $target;

        $row = GoalProgress::firstOrNew([
            'campaign_id' => $campaign->id,
            'agent_id' => $agentId,
        ]);
        $row->progress = $progress;
        if ($achieved && ! $row->achieved_at) {
            $row->achieved_at = Carbon::now();
        } elseif (! $achieved) {
            $row->achieved_at = null;
        }
        $row->save();

        if ($toutesLesDates->isNotEmpty()) {
            $enrollment = GoalEnrollment::where('campaign_id', $campaign->id)
                ->where('agent_id', $agentId)
                ->first();
            if ($enrollment && $enrollment->locked_at === null) {
                $enrollment->update(['locked_at' => Carbon::now()]);
            }
        }

        return [
            'progress' => $progress,
            'target' => $target,
            'achieved' => $achieved,
            'achieved_at' => $row->achieved_at,
            'reward' => $option?->reward ?? 0,
        ];
    }
}
