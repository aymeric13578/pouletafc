<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vente au poids : prix du kilo, et poids saisi sur une commande.
 *
 * Le comptoir doit pouvoir corriger un panier en saisissant un nombre de kilos
 * — un poulet n'est pesé qu'au moment de la préparation, et le montant commandé
 * ne correspond presque jamais au poids réel. Jusqu'ici la commande restait
 * figée sur le prix annoncé et l'écart se réglait de la main à la main.
 *
 * Deux colonnes, nullables toutes les deux :
 *
 *  - parameters.price_per_kg : le tarif, réglé depuis l'écran Configuration au
 *    même titre que les autres pourcentages. Nullable car les grilles déjà en
 *    base n'en ont pas, et une grille sans tarif doit rester utilisable ;
 *
 *  - order_details.poids_kg : le poids retenu, gardé pour que l'écran puisse
 *    rouvrir le panier sur la valeur saisie plutôt que de la faire deviner, et
 *    pour qu'une correction reste vérifiable après coup. Nullable : les
 *    commandes qui n'ont pas été pesées n'en portent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parameters') && ! Schema::hasColumn('parameters', 'price_per_kg')) {
            Schema::table('parameters', function (Blueprint $table) {
                $table->integer('price_per_kg')->nullable()->after('vip_percentage');
            });
        }

        if (Schema::hasTable('order_details') && ! Schema::hasColumn('order_details', 'poids_kg')) {
            Schema::table('order_details', function (Blueprint $table) {
                // Décimal plutôt qu'entier : on pèse au demi-kilo près.
                $table->decimal('poids_kg', 8, 3)->nullable()->after('panier_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('parameters') && Schema::hasColumn('parameters', 'price_per_kg')) {
            Schema::table('parameters', function (Blueprint $table) {
                $table->dropColumn('price_per_kg');
            });
        }

        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'poids_kg')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('poids_kg');
            });
        }
    }
};
