<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de retrait des agents — bouton "Demander un retrait" (finances,
 * app agent). Ne déclenche aucun virement automatique : la demande est
 * seulement journalisée ici, visible au tableau de bord pour validation
 * manuelle, exactement comme l'app agent le promet à l'agent ("le service
 * Clando va vous contacter").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('withdrawal_requests')) {
            return;
        }

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_agent');
            $table->decimal('amount', 12, 2);
            // pending -> validated (le tableau de bord confirme avoir pris
            // contact) ; pas de statut "rejected" pour l'instant, aucune
            // demande formulée en ce sens.
            $table->string('status')->default('pending');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index('id_agent');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
