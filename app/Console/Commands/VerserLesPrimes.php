<?php

namespace App\Console\Commands;

use App\Models\GoalCampaign;
use App\Models\GoalProgress;
use App\Support\LivreDeComptes;
use App\Support\ObjectifProgression;
use Illuminate\Console\Command;

/**
 * Verse les primes des campagnes d'objectifs arrivées à échéance.
 *
 * Règle validée le 2026-09-01 : la prime est créditée automatiquement à la
 * date de fin de la campagne quand l'objectif est atteint — pas de
 * validation manuelle, pas de prorata en cas d'objectif manqué.
 *
 * Pour chaque campagne terminée et non encore réglée :
 *  1. la progression de chaque agent engagé est figée
 *     (ObjectifProgression::figerALaCloture — écrit frozen_progress et
 *     amount_due, 0 si l'objectif n'est pas atteint) ;
 *  2. chaque amount_due > 0 est crédité au livre de comptes, libellé
 *     « Prime — <titre de la campagne> » ;
 *  3. la campagne est marquée settled_at pour ne plus jamais être
 *     retraitée — l'idempotence du livre protège déjà d'un double
 *     versement, ce marqueur évite surtout de re-figer une campagne close
 *     dont les données auraient bougé depuis.
 *
 * Planifiée chaque nuit (voir Console\Kernel) ; relançable à la main sans
 * risque à tout moment.
 */
class VerserLesPrimes extends Command
{
    protected $signature = 'primes:verser';

    protected $description = "Fige et crédite les primes des campagnes d'objectifs terminées";

    public function handle(LivreDeComptes $livre): int
    {
        $campagnes = GoalCampaign::whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereNull('settled_at')
            ->get();

        if ($campagnes->isEmpty()) {
            $this->info('Aucune campagne à régler.');

            return self::SUCCESS;
        }

        foreach ($campagnes as $campagne) {
            ObjectifProgression::figerALaCloture($campagne);

            $dues = GoalProgress::where('campaign_id', $campagne->id)
                ->where('amount_due', '>', 0)
                ->get();

            foreach ($dues as $du) {
                $livre->prime(
                    (int) $du->agent_id,
                    (float) $du->amount_due,
                    (int) $campagne->id,
                    (string) $campagne->title,
                );
            }

            $campagne->update(['settled_at' => now()]);

            $this->info(sprintf(
                'Campagne "%s" réglée : %d prime(s) versée(s).',
                $campagne->title,
                $dues->count(),
            ));
        }

        return self::SUCCESS;
    }
}
