<?php

namespace App\Console\Commands;

use App\Fonction\Fonction;
use App\Models\Agent;
use App\Support\LivreDeComptes;
use Illuminate\Console\Command;

/**
 * Bascule choisie le 2026-09-01 : « report à nouveau ». Le solde actuel de
 * chaque agent (ancienne formule, Fonction::solde) est figé tel quel comme
 * première ligne de son compte au livre — personne ne voit son solde changer
 * le jour de la bascule, les nouvelles règles ne s'appliquent qu'aux
 * mouvements suivants.
 *
 * Relançable sans risque : le report d'ouverture est idempotent par acteur
 * (clé unique), un agent déjà ouvert est simplement sauté — ce qui permet
 * aussi de rattraper les agents créés entre deux exécutions.
 */
class OuvrirLeLivreDeComptes extends Command
{
    protected $signature = 'finances:ouvrir';

    protected $description = "Fige le solde actuel de chaque agent comme report à nouveau du livre de comptes";

    public function handle(LivreDeComptes $livre): int
    {
        $fonction = new Fonction();
        $ouverts = 0;

        // `id` doit faire partie du select : chunkById pagine dessus, et sans
        // lui la deuxième page ne démarre jamais (curseur nul).
        Agent::query()->select('id', 'id_user')->whereNotNull('id_user')->chunkById(100, function ($agents) use ($livre, $fonction, &$ouverts) {
            foreach ($agents as $agent) {
                $solde = (float) ($fonction->solde($agent->id_user)['solde'] ?? 0);
                $livre->reportOuverture('agent', (int) $agent->id_user, $solde);
                $ouverts++;
            }
        });

        $this->info("$ouverts agent(s) parcourus — les reports déjà écrits ont été ignorés (idempotence).");

        return self::SUCCESS;
    }
}
