<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille tarifaire des courses de coursier (envoi de colis entre
 * particuliers, écran « Commander un Coursier » de l'application cliente).
 *
 * Le prix était écrit en dur côté application : 500 F de prise en charge
 * + 200 F/km (voir CoursierRequestScreen). Le changer demandait donc de
 * republier l'application, alors que les tarifs clando et livraison, eux,
 * se règlent depuis la grille tarifaire du tableau de bord.
 *
 * Deux colonnes, sur le même modèle que le couple clando_kilometer /
 * min_price_clando : le prix au kilomètre et le montant plancher (qui sert
 * aussi de prise en charge). Nullable et sans valeur par défaut : les
 * grilles déjà enregistrées n'en portent pas, et l'application retombe
 * alors sur ses valeurs historiques plutôt que sur un tarif à zéro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        Schema::table('parameters', function (Blueprint $table) {
            if (! Schema::hasColumn('parameters', 'coursier_kilometer')) {
                $table->integer('coursier_kilometer')->nullable()->after('command_kilometer');
            }
            if (! Schema::hasColumn('parameters', 'min_price_coursier')) {
                $table->integer('min_price_coursier')->nullable()->after('min_price_command');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        Schema::table('parameters', function (Blueprint $table) {
            foreach (['coursier_kilometer', 'min_price_coursier'] as $colonne) {
                if (Schema::hasColumn('parameters', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
