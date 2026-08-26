<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Même colonne que clando.agent_arrived_at (voir
 * 2026_08_26_000006_arrivee_agent_clando.php), pour les livraisons
 * boutique : l'agent signale son arrivée avant de saisir le code de
 * livraison, pour que l'application cliente ouvre d'elle-même l'écran de
 * paiement Orange Money.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_details') && ! Schema::hasColumn('order_details', 'agent_arrived_at')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->timestamp('agent_arrived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'agent_arrived_at')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('agent_arrived_at');
            });
        }
    }
};
