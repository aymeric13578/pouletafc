<?php

namespace App\Support;

use App\Models\MouvementFinancier;
use Illuminate\Database\QueryException;

/**
 * Le seul point d'écriture du livre de comptes (`mouvements_financiers`).
 *
 * Une méthode par événement métier, chacune décidant de l'acteur, du sens et
 * du libellé — l'appelant ne fournit que les montants et la source. Les
 * montants eux-mêmes (commissions, majorations) restent calculés en amont
 * par GrilleTarifaire/MajorationBoutique : ce service enregistre, il ne
 * tarife pas.
 *
 * Règles d'argent validées avec le propriétaire le 2026-09-01 :
 *  - course/coursier CASH  → agent débité de la seule commission (il garde
 *    le prix plein en main) ;
 *  - course/coursier OM    → agent crédité de (prix − commission), société
 *    créditée de la commission ;
 *  - livraison             → même logique, la commission portant sur les
 *    frais de livraison ;
 *  - prime                 → créditée automatiquement à la fin de campagne ;
 *  - vente boutique OM     → boutique créditée net de majoration, société
 *    créditée de la majoration.
 *
 * Idempotence : chaque écriture porte une clé unique dérivée de l'événement
 * (type + source). Rejouer le même événement — retry réseau, tâche relancée —
 * retombe sur la contrainte d'unicité et est traité comme "déjà enregistré",
 * jamais comme une erreur ni comme une deuxième ligne.
 *
 * Phase 1 : double écriture. Ce livre s'écrit en parallèle des mécanismes
 * existants (Fonction::solde, deposit_recu) sans changer aucun affichage —
 * la commande finances:reconcilier compare les deux mondes avant bascule.
 * C'est aussi pourquoi chaque méthode avale l'échec au lieu de le propager :
 * tant que l'existant reste la référence affichée, une panne du livre ne
 * doit jamais faire échouer une fin de course.
 */
class LivreDeComptes
{
    // ── Écritures agent ─────────────────────────────────────────────────

    /** Course ou coursier payé en espèces : l'agent doit la commission. */
    public function courseCash(int $idAgent, float $commission, string $sourceType, int $sourceId, string $ref): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::DEBIT, MouvementFinancier::COMMISSION_COURSE,
            $commission, $sourceType, $sourceId,
            "Commission — course #$ref (espèces)",
        );
    }

    /** Course ou coursier payé Orange Money : l'agent reçoit sa part. */
    public function courseOm(int $idAgent, float $prix, float $commission, string $sourceType, int $sourceId, string $ref): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::CREDIT, MouvementFinancier::GAIN_COURSE_OM,
            $prix - $commission, $sourceType, $sourceId,
            "Gain — course #$ref (Orange Money)",
        );
        $this->ecrire(
            MouvementFinancier::ACTEUR_SOCIETE, null,
            MouvementFinancier::CREDIT, MouvementFinancier::COMMISSION_SOCIETE,
            $commission, $sourceType, $sourceId,
            "Commission — course #$ref",
        );
    }

    /** Livraison payée en espèces : commission sur les frais de livraison. */
    public function livraisonCash(int $idAgent, float $commission, int $idOrder, string $ref): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::DEBIT, MouvementFinancier::COMMISSION_LIVRAISON,
            $commission, 'order', $idOrder,
            "Commission — livraison #$ref (espèces)",
        );
    }

    /** Livraison payée Orange Money : l'agent reçoit (frais − commission). */
    public function livraisonOm(int $idAgent, float $frais, float $commission, int $idOrder, string $ref): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::CREDIT, MouvementFinancier::GAIN_LIVRAISON_OM,
            $frais - $commission, 'order', $idOrder,
            "Gain — livraison #$ref (Orange Money)",
        );
        $this->ecrire(
            MouvementFinancier::ACTEUR_SOCIETE, null,
            MouvementFinancier::CREDIT, MouvementFinancier::COMMISSION_SOCIETE,
            $commission, 'order', $idOrder,
            "Commission — livraison #$ref",
        );
    }

    /** Prime d'objectif, versée à la fin de la campagne. */
    public function prime(int $idAgent, float $montant, int $idCampagne, string $titreCampagne): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::CREDIT, MouvementFinancier::PRIME,
            $montant, 'goal_campaign', $idCampagne,
            "Prime — $titreCampagne",
        );
    }

    /** Dépôt (remise d'espèces) de l'agent, validé. */
    public function depot(int $idAgent, float $montant, int $idDepot): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::CREDIT, MouvementFinancier::DEPOT,
            $montant, 'deposit', $idDepot,
            'Dépôt validé',
        );
    }

    /** Retrait agent validé au tableau de bord. */
    public function retrait(int $idAgent, float $montant, int $idDemande): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_AGENT, $idAgent,
            MouvementFinancier::DEBIT, MouvementFinancier::RETRAIT,
            $montant, 'withdrawal_request', $idDemande,
            'Retrait validé',
        );
    }

    /** Retrait marchand validé au tableau de bord — même page, colonne Type. */
    public function retraitBoutique(int $idBoutique, float $montant, int $idDemande): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_BOUTIQUE, $idBoutique,
            MouvementFinancier::DEBIT, MouvementFinancier::RETRAIT,
            $montant, 'withdrawal_request', $idDemande,
            'Retrait validé',
        );
    }

    /**
     * Abonnement mensuel d'une boutique, prélevé à son échéance. Le solde
     * peut devenir négatif (règle validée le 2026-09-01) : les prochaines
     * ventes OM le remboursent en premier. La clé inclut l'échéance : le
     * prélèvement du mois suivant est un événement distinct, relancer la
     * commande le même jour n'écrit rien deux fois.
     */
    public function abonnement(int $idBoutique, float $montant, string $echeance): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_BOUTIQUE, $idBoutique,
            MouvementFinancier::DEBIT, MouvementFinancier::ABONNEMENT,
            $montant, 'boutique_facturation', $idBoutique,
            "Abonnement — échéance du $echeance",
            "abonnement|$idBoutique|$echeance",
        );
    }

    // ── Écritures boutique / société ────────────────────────────────────

    /** Vente boutique payée OM : le marchand est crédité net de majoration. */
    public function venteOm(int $idBoutique, float $montantNet, float $majoration, string $sourceType, int $sourceId, string $ref): void
    {
        $this->ecrire(
            MouvementFinancier::ACTEUR_BOUTIQUE, $idBoutique,
            MouvementFinancier::CREDIT, MouvementFinancier::VENTE_OM,
            $montantNet, $sourceType, $sourceId,
            "Vente #$ref (Orange Money)",
        );

        if ($majoration > 0) {
            $this->ecrire(
                MouvementFinancier::ACTEUR_SOCIETE, null,
                MouvementFinancier::CREDIT, MouvementFinancier::MAJORATION,
                $majoration, $sourceType, $sourceId,
                "Majoration — vente #$ref",
                // La boutique fait partie de la clé : une commande peut mêler
                // plusieurs boutiques, chacune apportant SA majoration à la
                // société sur la même source — sans ça, seule la première
                // serait écrite (collision de clé).
                MouvementFinancier::MAJORATION . "|societe|$sourceType|$sourceId|boutique|$idBoutique",
            );
        }
    }

    // ── Ouverture & solde ───────────────────────────────────────────────

    /**
     * Fige le solde existant d'un acteur comme point de départ du livre.
     * Idempotent par acteur : rejouer la commande d'ouverture ne crée
     * jamais deux reports pour la même personne.
     */
    public function reportOuverture(string $acteurType, ?int $acteurId, float $solde): void
    {
        $this->ecrire(
            $acteurType, $acteurId,
            $solde >= 0 ? MouvementFinancier::CREDIT : MouvementFinancier::DEBIT,
            MouvementFinancier::REPORT_OUVERTURE,
            abs($solde), null, null,
            'Report à nouveau (bascule livre de comptes)',
            "ouverture|$acteurType|" . ($acteurId ?? 'societe'),
        );
    }

    /** Le solde d'un acteur : somme des crédits moins somme des débits. */
    public function solde(string $acteurType, ?int $acteurId): float
    {
        $base = MouvementFinancier::where('acteur_type', $acteurType)
            ->where('acteur_id', $acteurId);

        $credits = (clone $base)->where('sens', MouvementFinancier::CREDIT)->sum('montant');
        $debits = (clone $base)->where('sens', MouvementFinancier::DEBIT)->sum('montant');

        return round((float) $credits - (float) $debits, 2);
    }

    // ── Cœur ────────────────────────────────────────────────────────────

    private function ecrire(
        string $acteurType,
        ?int $acteurId,
        string $sens,
        string $type,
        float $montant,
        ?string $sourceType,
        ?int $sourceId,
        string $libelle,
        ?string $cle = null,
    ): void {
        // Un mouvement nul n'apprend rien à personne et gonflerait le livre.
        if (round($montant, 2) == 0.0) {
            return;
        }

        try {
            MouvementFinancier::create([
                'acteur_type' => $acteurType,
                'acteur_id' => $acteurId,
                'sens' => $sens,
                'type' => $type,
                'montant' => round($montant, 2),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'libelle' => $libelle,
                'idempotency_key' => $cle ?? $this->cle($type, $acteurType, $acteurId, $sourceType, $sourceId),
            ]);
        } catch (QueryException $e) {
            // Contrainte d'unicité sur la clé : l'événement est déjà au
            // livre, exactement le comportement voulu sur un rejeu. Toute
            // autre erreur est journalisée sans être propagée — en Phase 1
            // l'existant reste la référence, une panne du livre ne doit pas
            // faire échouer l'opération métier qui l'a déclenchée.
            if (! $this->estUnDoublon($e)) {
                report($e);
            }
        }
    }

    private function cle(string $type, string $acteurType, ?int $acteurId, ?string $sourceType, ?int $sourceId): ?string
    {
        if ($sourceType === null || $sourceId === null) {
            return null;
        }

        return "$type|$acteurType|" . ($acteurId ?? 'societe') . "|$sourceType|$sourceId";
    }

    private function estUnDoublon(QueryException $e): bool
    {
        // 23000 : violation de contrainte d'intégrité (MySQL 1062, SQLite
        // 2067...) — le code SQLSTATE est portable, le code driver non.
        return ($e->errorInfo[0] ?? null) === '23000';
    }
}
