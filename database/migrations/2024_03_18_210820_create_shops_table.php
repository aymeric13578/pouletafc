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
        if (Schema::hasTable('shops')) {
            return;
        }

        Schema::create('shops', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('shop_name');
            $table->string('shop_code')->nullable();
            $table->string('shop_brand')->nullable();
            $table->string('shop_telephone1')->nullable();
            $table->string('shop_telephone2')->nullable();
            $table->string('shop_telephone3')->nullable();
            $table->string('shop_address')->nullable();
            $table->string('shop_email1')->nullable();
            $table->string('shop_email2')->nullable();
            $table->string('shop_commercial_register')->nullable();
            $table->string('shop_commercial_register_file')->nullable();
            $table->integer('product_count')->default(0);
            $table->string('shop_logo')->nullable();
            $table->string('slug')->nullable();
            $table->string('seller_id');

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
        Schema::dropIfExists('shops');
    }
};
