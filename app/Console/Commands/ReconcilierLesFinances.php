<?php

namespace App\Console\Commands;

use App\Fonction\Fonction;
use App\Models\Agent;
use App\Models\MouvementFinancier;
use App\Support\LivreDeComptes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Contrôle de la double écriture (Phase 1) — deux vérifications :
 *
 *  1. Couverture des événements : toute course/commande passée à Success
 *     avec un payment_method APRÈS la bascule doit avoir sa ligne au livre.
 *     Une absence signifie qu'un point d'écriture a été raté ou a échoué —
 *     c'est LE signal qui doit rester à zéro avant toute bascule
 *     d'affichage.
 *
 *  2. Soldes côte à côte : ancienne formule vs livre, par agent. Un écart
 *     ici est ATTENDU dès qu'une course est passée depuis la bascule — les
 *     deux mondes n'appliquent pas les mêmes règles (l'ancien déduit le
 *     prix plein, le livre la seule commission). La colonne sert à mesurer
 *     et expliquer la divergence, pas à exiger zéro.
 */
class ReconcilierLesFinances extends Command
{
    protected $signature = 'finances:reconcilier {--agents=10 : Nombre d\'agents à afficher dans le tableau des soldes}';

    protected $description = 'Compare le livre de comptes à l\'existant : événements manquants et soldes côte à côte';

    public function handle(LivreDeComptes $livre): int
    {
        $ouverture = MouvementFinancier::where('type', MouvementFinancier::REPORT_OUVERTURE)
            ->min('created_at');

        if ($ouverture === null) {
            $this->warn("Aucun report d'ouverture : lancez d'abord finances:ouvrir.");

            return self::FAILURE;
        }

        $this->info("Bascule : $ouverture");
        $this->newLine();

        // ── 1. Événements manquants ─────────────────────────────────────
        $manquantsClando = DB::table('clando')
            ->where('status', 'Success')
            ->whereNotNull('payment_method')
            ->where('updated_at', '>=', $ouverture)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mouvements_financiers')
                    ->whereColumn('mouvements_financiers.source_id', 'clando.id')
                    ->where('mouvements_financiers.source_type', 'clando')
                    ->where('mouvements_financiers.acteur_type', MouvementFinancier::ACTEUR_AGENT);
            })
            ->count();

        $manquantsOrder = DB::table('order_details')
            ->where('status', 'Success')
            ->whereNotNull('payment_method')
            ->where('updated_at', '>=', $ouverture)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mouvements_financiers')
                    ->whereColumn('mouvements_financiers.source_id', 'order_details.id')
                    ->where('mouvements_financiers.source_type', 'order')
                    ->where('mouvements_financiers.acteur_type', MouvementFinancier::ACTEUR_AGENT);
            })
            ->count();

        $this->line("Courses clando Success sans ligne au livre : <options=bold>$manquantsClando</>");
        $this->line("Commandes Success sans ligne au livre      : <options=bold>$manquantsOrder</>");
        if ($manquantsClando + $manquantsOrder > 0) {
            $this->warn('→ Des événements échappent à la double écriture : à corriger avant toute bascule.');
        } else {
            $this->info('→ Couverture complète depuis la bascule.');
        }
        $this->newLine();

        // ── 2. Soldes côte à côte ───────────────────────────────────────
        $fonction = new Fonction();
        $lignes = [];

        foreach (Agent::query()->whereNotNull('id_user')->limit((int) $this->option('agents'))->get(['id_user', 'agent_name']) as $agent) {
            // Depuis la bascule, Fonction::solde() lit le livre : la
            // comparaison n'a de sens que contre l'ancienne formule.
            $ancien = (float) ($fonction->soldeAncienneFormule($agent->id_user)['solde'] ?? 0);
            $nouveau = $livre->solde(MouvementFinancier::ACTEUR_AGENT, (int) $agent->id_user);
            $lignes[] = [
                $agent->agent_name ?? $agent->id_user,
                number_format($ancien, 0, ',', ' '),
                number_format($nouveau, 0, ',', ' '),
                number_format($nouveau - $ancien, 0, ',', ' '),
            ];
        }

        $this->table(['Agent', 'Ancienne formule', 'Livre de comptes', 'Écart (attendu si activité)'], $lignes);

        return self::SUCCESS;
    }
}
