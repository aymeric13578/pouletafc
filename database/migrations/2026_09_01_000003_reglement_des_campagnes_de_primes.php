<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque une campagne d'objectifs comme réglée : ses primes ont été versées
 * au livre de comptes (commande primes:verser). Sans ce marqueur, la
 * commande re-figerait la progression de toutes les campagnes passées à
 * chaque exécution — l'idempotence du livre empêche un double versement,
 * mais recalculer une campagne close des mois plus tard pourrait changer
 * son amount_due si les données de courses ont bougé depuis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goal_campaigns') || Schema::hasColumn('goal_campaigns', 'settled_at')) {
            return;
        }

        Schema::table('goal_campaigns', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('goal_campaigns') && Schema::hasColumn('goal_campaigns', 'settled_at')) {
            Schema::table('goal_campaigns', function (Blueprint $table) {
                $table->dropColumn('settled_at');
            });
        }
    }
};
