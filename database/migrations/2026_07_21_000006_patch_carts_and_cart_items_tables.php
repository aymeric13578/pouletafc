<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App\Models\Cart et App\Models\CartItem référencent des colonnes
 * (total_amount / user_id, status, price) absentes des migrations
 * d'origine mais utilisées par le flux de commande (mobile et web).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->nullable();
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (! Schema::hasColumn('cart_items', 'status')) {
                $table->string('status')->nullable();
            }
            if (! Schema::hasColumn('cart_items', 'price')) {
                $table->decimal('price', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            foreach (['user_id', 'status', 'price'] as $column) {
                if (Schema::hasColumn('cart_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
