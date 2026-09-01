<?php

namespace App\Console\Commands;

use App\Models\BoutiqueFacturation;
use App\Support\LivreDeComptes;
use Illuminate\Console\Command;

/**
 * Prélève au livre de comptes l'abonnement des boutiques arrivées à
 * échéance — Phase 2, règles validées le 2026-09-01 :
 *
 *  - la date d'échéance est propre à chaque boutique
 *    (boutique_facturations.abonnement_echeance, gérée au tableau de bord) ;
 *  - le solde peut devenir négatif : le prélèvement passe quoi qu'il
 *    arrive, les prochaines ventes Orange Money le remboursent en premier ;
 *  - après prélèvement, l'échéance avance d'une période (mensuel,
 *    trimestriel, annuel) — le même rythme que le rappel déjà affiché au
 *    marchand dans son app.
 *
 * Idempotente : la clé du mouvement inclut l'échéance prélevée — relancer
 * la commande le même jour n'écrit rien deux fois, et une échéance ratée
 * (cron en panne un jour) est rattrapée au passage suivant puisque la date
 * reste dans le passé tant qu'elle n'a pas été traitée.
 */
class PreleverLesAbonnements extends Command
{
    protected $signature = 'abonnements:prelever';

    protected $description = "Prélève l'abonnement des boutiques arrivées à échéance et avance leur prochaine date";

    public function handle(LivreDeComptes $livre): int
    {
        $dues = BoutiqueFacturation::where('actif', true)
            ->where('mode', 'abonnement')
            ->whereNotNull('abonnement_montant')
            ->whereNotNull('abonnement_echeance')
            ->whereDate('abonnement_echeance', '<=', now()->toDateString())
            ->get();

        if ($dues->isEmpty()) {
            $this->info('Aucun abonnement à prélever.');

            return self::SUCCESS;
        }

        foreach ($dues as $facturation) {
            $echeance = $facturation->abonnement_echeance instanceof \DateTimeInterface
                ? $facturation->abonnement_echeance->format('Y-m-d')
                : (string) $facturation->abonnement_echeance;

            $livre->abonnement(
                (int) $facturation->shop_id,
                (float) $facturation->abonnement_montant,
                $echeance,
            );

            $mois = BoutiqueFacturation::PERIODICITES[$facturation->abonnement_periodicite] ?? 1;
            $facturation->update([
                'abonnement_echeance' => \Carbon\Carbon::parse($echeance)->addMonths($mois),
            ]);

            $this->info(sprintf(
                'Boutique #%d : %s F prélevés (échéance %s), prochaine échéance dans %d mois.',
                $facturation->shop_id,
                number_format((float) $facturation->abonnement_montant, 0, ',', ' '),
                $echeance,
                $mois,
            ));
        }

        return self::SUCCESS;
    }
}
