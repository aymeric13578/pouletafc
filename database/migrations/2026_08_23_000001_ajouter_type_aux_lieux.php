<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Catégorie d'un lieu enregistré par un agent (boutique, carrefour, domicile,
 * bureau, autre) — jusqu'ici absente, un lieu n'était qu'un nom libre et des
 * coordonnées, impossible à filtrer ou à distinguer visuellement dans la liste.
 *
 * Nullable et sans valeur par défaut : les lieux déjà enregistrés n'ont pas de
 * catégorie connue, mieux vaut l'afficher comme "non renseigné" côté client que
 * de leur attribuer arbitrairement une valeur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('locations') || Schema::hasColumn('locations', 'type')) {
            return;
        }

        Schema::table('locations', function ($table) {
            $table->string('type', 32)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('locations') || ! Schema::hasColumn('locations', 'type')) {
            return;
        }

        Schema::table('locations', function ($table) {
            $table->dropColumn('type');
        });
    }
};
