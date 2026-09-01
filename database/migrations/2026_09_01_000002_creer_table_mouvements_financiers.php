<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le livre de comptes unique de l'écosystème — Phase 1 du plan « Circuits de
 * l'argent » (voir l'audit du 2026-09-01).
 *
 * Chaque franc qui bouge devient une ligne : un acteur (agent, boutique ou la
 * société), un sens (crédit/débit), un type d'événement, un montant, et la
 * référence de ce qui l'a provoqué. Tout solde est une somme de lignes —
 * jamais une formule à part qui pourrait diverger.
 *
 * En Phase 1 ce livre s'écrit EN PARALLÈLE de l'existant (double écriture) :
 * aucun chiffre affiché ne change, une commande de réconciliation compare les
 * deux mondes avant toute bascule. Les règles de montant (commissions,
 * majorations) restent calculées par GrilleTarifaire/MajorationBoutique —
 * ce livre enregistre, il ne tarife pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mouvements_financiers')) {
            return;
        }

        Schema::create('mouvements_financiers', function (Blueprint $table) {
            $table->id();

            // 'agent' | 'boutique' | 'societe'. Pour la société, acteur_id
            // reste nul : il n'y a qu'une seule société.
            $table->string('acteur_type', 20);
            $table->unsignedBigInteger('acteur_id')->nullable();

            // 'credit' : l'acteur reçoit ; 'debit' : l'acteur doit/verse.
            $table->string('sens', 10);

            // Voir les constantes de App\Models\MouvementFinancier — un type
            // par événement métier (commission_course, gain_course_om,
            // prime, depot, retrait, vente_om, majoration, abonnement,
            // report_ouverture...).
            $table->string('type', 40);

            $table->decimal('montant', 12, 2);

            // Ce qui a provoqué le mouvement (course, commande, paiement,
            // demande de retrait, campagne de prime...). Nullable : un report
            // d'ouverture n'a pas de source.
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Ce que l'acteur lit dans son historique ("Prime — Objectif
            // d'août", "Course #REF_x en espèces"...).
            $table->string('libelle')->nullable();

            /*
             | Rejouer le même événement (retry réseau, double appel, tâche
             | planifiée relancée) ne doit jamais produire deux lignes : la
             | clé est unique, la deuxième insertion échoue proprement et le
             | service la traite comme "déjà enregistré".
             */
            $table->string('idempotency_key')->nullable()->unique();

            $table->timestamps();

            $table->index(['acteur_type', 'acteur_id']);
            $table->index(['source_type', 'source_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_financiers');
    }
};
