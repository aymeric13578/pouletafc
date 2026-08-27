<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clés d'idempotence — évite qu'un double-tap ou un retry réseau sur un
 * bouton sensible (ex. "Prendre" une course) ne s'exécute deux fois. Le
 * client envoie une clé générée une fois par tentative logique ; un second
 * appel avec la même clé reçoit la réponse déjà produite au lieu de
 * relancer l'opération. Voir App\Support\Idempotence.
 *
 * Portée volontairement limitée à takeClandoCommand/takeOrderCommand pour
 * l'instant : ce sont les deux seules actions sensibles sans déjà un
 * verrou de ligne (contrairement à terminatedCourse/terminatedCourseOrder,
 * protégées par DB::transaction + lockForUpdate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('endpoint', 100);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamps();

            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
