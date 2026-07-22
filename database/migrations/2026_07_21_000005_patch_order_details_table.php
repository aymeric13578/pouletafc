<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\order_detail (utilisé par l'API mobile ET la nouvelle boutique
 * web) référence de nombreuses colonnes absentes de la migration d'origine
 * de order_details, et plusieurs colonnes historiques sont NOT NULL sans
 * valeur par défaut alors qu'aucun des flux de création de commande (mobile
 * ou web) ne les renseigne. On ajoute les colonnes manquantes et on assouplit
 * les anciennes contraintes NOT NULL.
 */
return new class extends Migration
{
    protected array $newColumns = [
        'date', 'id_cart', 'longitude', 'latitude', 'id_user', 'ref',
        'payment_method', 'commission_seller', 'id_agent', 'delivery_type',
        'latAgent', 'lonAgent', 'matricule_vehicule', 'address', 'latShop',
        'lonShop', 'shop_name', 'delivery_code', 'commission_agent',
        'status_paiement', 'delivery_fees', 'panier_price',
    ];

    protected array $relaxColumns = [
        'id_order', 'product_name', 'id_customer', 'subtotal', 'discount', 'tax', 'total_ttc',
    ];

    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            foreach ($this->newColumns as $column) {
                if (! Schema::hasColumn('order_details', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            foreach ($this->relaxColumns as $column) {
                if (Schema::hasColumn('order_details', $column)) {
                    $table->string($column)->nullable()->change();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            foreach ($this->newColumns as $column) {
                if (Schema::hasColumn('order_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
