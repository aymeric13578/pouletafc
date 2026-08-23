<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GoalCampaign;
use App\Models\GoalEnrollment;
use App\Support\ObjectifProgression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Objectifs et primes — application agent.
 *
 * Un agent ne peut s'engager que sur une seule option par campagne
 * (contrainte unique campaign_id+agent_id sur goal_enrollments), et
 * l'engagement se verrouille dès la première course comptabilisée
 * (locked_at) : voir le document de conception du 2026-08-23. La création,
 * la publication et la clôture des campagnes se font depuis le tableau de
 * bord (page Folio dashboard/objectifs.blade.php) — ce contrôleur ne couvre
 * que la lecture et l'engagement côté agent. Le calcul de progression vit
 * dans App\Support\ObjectifProgression, partagé avec le tableau de bord.
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
                $progress = ObjectifProgression::calculerEtEnregistrer($campaign, $agentId, (int) $enrollment->option_id);
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
}
