<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point de retrait par défaut.
 *
 * Quand le client ne choisit pas sa zone, le point de livraison retombait sur
 * users.latitude/longitude — la dernière position connue du compte. Pour un
 * compte jamais localisé, ces colonnes portent toutes la même valeur : d'où un
 * point de retrait identique pour tout le monde, parfois à des centaines de
 * kilomètres de Garoua.
 *
 * L'administrateur désigne désormais un lieu de repli parmi ceux que les agents
 * ont enregistrés, plutôt que de laisser le hasard d'une colonne périmée
 * décider.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parameters') && ! Schema::hasColumn('parameters', 'default_pickup_location_id')) {
            Schema::table('parameters', function (Blueprint $table) {
                /*
                 | Sans clé étrangère : la table locations est alimentée par les
                 | agents depuis leur téléphone et peut voir des lieux
                 | supprimés. Une contrainte ferait échouer la suppression d'un
                 | lieu devenu inutile ; le code vérifie l'existence à la
                 | lecture, et retombe proprement si le lieu a disparu.
                 */
                $table->unsignedBigInteger('default_pickup_location_id')->nullable()->after('vip_percentage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('parameters') && Schema::hasColumn('parameters', 'default_pickup_location_id')) {
            Schema::table('parameters', function (Blueprint $table) {
                $table->dropColumn('default_pickup_location_id');
            });
        }
    }
};
