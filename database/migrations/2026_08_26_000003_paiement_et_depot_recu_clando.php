<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux colonnes pour le geste "Terminer" côté agent (Cash reçu / Orange
 * Money) :
 *
 *  - clando.payment_method : comment la course a été réglée. N'existait pas
 *    du tout sur cette table (contrairement à order_details, qui a déjà son
 *    équivalent) — ClandoController::terminatedCourse ne pouvait donc rien
 *    enregistrer même si l'agent le précisait.
 *
 *  - agents.deposit_recu : le cumul de ce que l'agent a effectivement reçu
 *    (espèces en main, ou Orange Money confirmé) pour les courses qu'il a
 *    terminées — délibérément séparée de Fonction::solde() et
 *    Agent::getBalanceAttribute(), qui divergent déjà entre elles sur la
 *    définition du solde ; ce cumul est un chiffre neuf, pas une correction
 *    de l'un ou l'autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clando') && ! Schema::hasColumn('clando', 'payment_method')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->after('status_paiement');
            });
        }

        if (Schema::hasTable('agents') && ! Schema::hasColumn('agents', 'deposit_recu')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->decimal('deposit_recu', 12, 2)->default(0)->after('balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clando') && Schema::hasColumn('clando', 'payment_method')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'deposit_recu')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropColumn('deposit_recu');
            });
        }
    }
};
