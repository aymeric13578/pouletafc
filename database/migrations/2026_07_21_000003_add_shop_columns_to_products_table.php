<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le modèle App\Models\Product (et le contrôleur mobile API\ProductsController)
 * référencent des colonnes absentes des migrations versionnées d'origine
 * (name, price, stock_init, img, ref, id_category, id_sub_category, id_shop,
 * id_merchand) — probablement ajoutées manuellement en production. On les
 * ajoute ici de façon additive, sans toucher aux colonnes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 12, 2)->nullable()->after('name');
            }
            if (! Schema::hasColumn('products', 'stock_init')) {
                $table->integer('stock_init')->nullable()->after('price');
            }
            if (! Schema::hasColumn('products', 'img')) {
                $table->string('img')->nullable();
            }
            if (! Schema::hasColumn('products', 'ref')) {
                $table->string('ref')->nullable();
            }
            if (! Schema::hasColumn('products', 'id_category')) {
                $table->unsignedBigInteger('id_category')->nullable();
            }
            if (! Schema::hasColumn('products', 'id_sub_category')) {
                $table->unsignedBigInteger('id_sub_category')->nullable();
            }
            if (! Schema::hasColumn('products', 'id_shop')) {
                $table->unsignedBigInteger('id_shop')->nullable();
            }
            if (! Schema::hasColumn('products', 'id_merchand')) {
                $table->unsignedBigInteger('id_merchand')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['name', 'price', 'stock_init', 'img', 'ref', 'id_category', 'id_sub_category', 'id_shop', 'id_merchand'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
