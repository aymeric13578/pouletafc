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
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('designation_tech');
            $table->string('unity');
            $table->string('unit_price');
            $table->string('category');
            $table->string('stock');
            $table->string('status');
            $table->string('locality');
            $table->string('id_saler');
            $table->string('bar_code');
            $table->string('description');
            $table->string('commission');
            $table->string('product_image1');
            $table->string('product_image2');
            $table->string('product_image3');
            $table->string('product_image4');
            $table->string('product_image5');
            $table->string('product_image6');
            $table->string('product_image7');
            $table->string('product_image8');
            $table->string('product_image9');
            $table->string('product_image10');
            $table->string('product_video1');
            $table->string('product_video2');
            $table->string('product_video3');
            $table->string('product_length');
            $table->string('product_width');
            $table->string('product_epaisseur');
            $table->string('product_volume');
            $table->string('product_color');
            $table->string('product_weigth');
            $table->string('parameter1');
            $table->string('parameter2');
            $table->string('parameter3');
            $table->string('parameter4');
            $table->string('parameter5');
            $table->string('parameter6');
            $table->string('parameter7');
            $table->string('parameter8');
            $table->string('sale_at');

            $table->softDeletes();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
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
