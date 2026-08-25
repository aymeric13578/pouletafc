<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonne « banner » de la table shops.
 *
 * Ajoutée à Shop::$fillable et déjà utilisée par MaBoutiqueController
 * (getMyShop, verifiedShopUser, updateMyShop) sans que la colonne existe
 * réellement en base : toute tentative d'upload de bannière échouait avec
 * une erreur « unknown column » (500), silencieusement, faute de cette
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shops') && ! Schema::hasColumn('shops', 'banner')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->string('banner')->nullable()->after('logo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'banner')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('banner');
            });
        }
    }
};
