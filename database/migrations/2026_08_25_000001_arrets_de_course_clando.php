<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arrêts ajoutables à une course Clando déjà acceptée par un agent —
 * chaque arrêt majore le prix de 100 F par tranche de 10 minutes écoulée
 * depuis son ajout (App\Support\SurchargeArrets).
 *
 * `base_price` capture le prix d'origine (transport seul, fixé une fois à
 * la création par Insertclando) au moment du tout premier arrêt ajouté —
 * pas à la création elle-même, pour ne toucher à rien tant qu'aucun arrêt
 * n'est utilisé. `clando.price` reste le total affiché partout dans les
 * deux apps (client, agent) : base_price + stops_surcharge, recalculé à
 * chaque arrêt ajouté et une dernière fois à la fin de course.
 *
 * Défensif comme les migrations clando précédentes (pas de migration de
 * création suivie pour cette table dans ce dépôt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clando')) {
            if (! Schema::hasColumn('clando', 'base_price')) {
                Schema::table('clando', function (Blueprint $table) {
                    $table->decimal('base_price', 10, 2)->nullable();
                });
            }
            if (! Schema::hasColumn('clando', 'stops_surcharge')) {
                Schema::table('clando', function (Blueprint $table) {
                    $table->decimal('stops_surcharge', 10, 2)->default(0);
                });
            }
        }

        if (! Schema::hasTable('clando_stops')) {
            Schema::create('clando_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_clando');
                // Nullable : un arrêt ajouté depuis le résumé léger
                // (ActiveClandoBanner, sans suivi de position en direct)
                // n'a pas de coordonnée à donner — la majoration reste
                // basée sur le temps écoulé (created_at), pas sur la
                // position.
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lon', 10, 7)->nullable();
                $table->string('label')->nullable();
                $table->timestamps();
                $table->index('id_clando');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clando')) {
            if (Schema::hasColumn('clando', 'base_price')) {
                Schema::table('clando', function (Blueprint $table) {
                    $table->dropColumn('base_price');
                });
            }
            if (Schema::hasColumn('clando', 'stops_surcharge')) {
                Schema::table('clando', function (Blueprint $table) {
                    $table->dropColumn('stops_surcharge');
                });
            }
        }

        Schema::dropIfExists('clando_stops');
    }
};
