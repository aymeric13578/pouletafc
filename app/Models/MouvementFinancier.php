<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une ligne du livre de comptes — voir la migration
 * 2026_09_01_000002_creer_table_mouvements_financiers pour le contexte, et
 * App\Support\LivreDeComptes pour la seule façon légitime d'en créer.
 */
class MouvementFinancier extends Model
{
    protected $table = 'mouvements_financiers';

    // ── Acteurs ─────────────────────────────────────────────────────────
    public const ACTEUR_AGENT = 'agent';
    public const ACTEUR_BOUTIQUE = 'boutique';
    public const ACTEUR_SOCIETE = 'societe';

    // ── Sens ────────────────────────────────────────────────────────────
    public const CREDIT = 'credit';
    public const DEBIT = 'debit';

    // ── Types d'événement ───────────────────────────────────────────────
    /** Solde existant figé à la bascule — la seule ligne sans source. */
    public const REPORT_OUVERTURE = 'report_ouverture';

    /** Course/coursier payé cash : l'agent garde le prix, doit la commission. */
    public const COMMISSION_COURSE = 'commission_course';

    /** Course/coursier payé OM : la société encaisse, doit (prix − commission). */
    public const GAIN_COURSE_OM = 'gain_course_om';

    /** Livraison payée cash : commission calculée sur les frais de livraison. */
    public const COMMISSION_LIVRAISON = 'commission_livraison';

    /** Livraison payée OM : l'agent est crédité de (frais − commission). */
    public const GAIN_LIVRAISON_OM = 'gain_livraison_om';

    /** Part société d'une course/livraison payée OM. */
    public const COMMISSION_SOCIETE = 'commission_societe';

    /** Objectif atteint, versé automatiquement à la fin de la campagne. */
    public const PRIME = 'prime';

    /** Remise d'argent de l'agent à la société, validée. */
    public const DEPOT = 'depot';

    /** Demande de retrait validée au tableau de bord. */
    public const RETRAIT = 'retrait';

    /** Vente boutique payée OM — crédit du marchand, net de majoration. */
    public const VENTE_OM = 'vente_om';

    /** Majoration boutique perçue par la société sur une vente. */
    public const MAJORATION = 'majoration';

    /** Abonnement mensuel d'une boutique, prélevé à date (Phase 2). */
    public const ABONNEMENT = 'abonnement';

    protected $fillable = [
        'acteur_type',
        'acteur_id',
        'sens',
        'type',
        'montant',
        'source_type',
        'source_id',
        'libelle',
        'idempotency_key',
    ];

    protected $casts = [
        'montant' => 'float',
    ];
}
