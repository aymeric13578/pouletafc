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
 * Relançable sans risque — elle tourne d'ailleurs à chaque déploiement
 * (deploy-release.sh, étape 9) : le report d'ouverture est idempotent par
 * acteur (clé unique), un agent déjà ouvert est simplement sauté.
 *
 * Un agent qui possède déjà N'IMPORTE QUELLE ligne au livre est sauté tout
 * entier, pas seulement protégé par la clé du report : créé après la
 * bascule, son livre est né avec lui et constitue déjà toute sa vérité —
 * l'ancienne formule, alimentée en parallèle par les mêmes événements
 * (dépôts, courses, retraits), recompterait ce que le livre porte déjà, et
 * lui ajouter ce "report" reviendrait à tout compter deux fois.
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
                // Voir l'en-tête : un livre déjà entamé est déjà la vérité de
                // cet agent, y déposer un report recompterait tout en double.
                $dejaAuLivre = \App\Models\MouvementFinancier::where('acteur_type', 'agent')
                    ->where('acteur_id', $agent->id_user)
                    ->exists();
                if ($dejaAuLivre) {
                    continue;
                }

                // Ancienne formule, jamais Fonction::solde() : depuis la
                // bascule celui-ci lit le livre — l'utiliser ici figerait la
                // valeur du livre dans le livre (zéro pour un agent jamais
                // ouvert), au lieu du solde hérité qu'on veut reporter.
                $solde = (float) ($fonction->soldeAncienneFormule($agent->id_user)['solde'] ?? 0);
                $livre->reportOuverture('agent', (int) $agent->id_user, $solde);
                $ouverts++;
            }
        });

        $this->info("$ouverts agent(s) parcourus — les reports déjà écrits ont été ignorés (idempotence).");

        return self::SUCCESS;
    }
}
