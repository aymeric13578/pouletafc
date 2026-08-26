<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'agent signale son arrivée avant de terminer la course.
 *
 * Volontairement séparée de "status" (qui reste 'take'/'process' à ce
 * moment) : status est déjà comparé littéralement à une dizaine
 * d'endroits dans les deux applications (voir ARCHITECTURE.md) — y ajouter
 * une valeur intermédiaire risquerait de faire disparaître silencieusement
 * ces courses des listes "en cours" qui ne la connaîtraient pas. Une
 * colonne à part ne casse rien de l'existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clando') && ! Schema::hasColumn('clando', 'agent_arrived_at')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->timestamp('agent_arrived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clando') && Schema::hasColumn('clando', 'agent_arrived_at')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->dropColumn('agent_arrived_at');
            });
        }
    }
};
