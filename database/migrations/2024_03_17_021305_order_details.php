<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         | Table déjà présente sur le serveur, créée à la main avant que le dépôt
         | ne la décrive.
         |
         | Sans cette garde, migrate échoue ici — « table already exists » — et
         | toutes les migrations suivantes sont ignorées. Le déploiement se
         | poursuit malgré tout : le code part, le schéma non. Les
         | fonctionnalités arrivent alors en production sans les colonnes dont
         | elles dépendent, et échouent à l'usage sans que rien ne l'annonce.
         */
        if (Schema::hasTable('order_details')) {
            return;
        }

        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->string('id_order');
            $table->string('product_name');
            $table->string('id_customer');
            $table->string('price');
            $table->string('qty');
            $table->string('subtotal');
            $table->string('discount');
            $table->string('tax');
            $table->string('total_ttc');
            $table->string('email_customer');
            $table->string('phone_customer');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
