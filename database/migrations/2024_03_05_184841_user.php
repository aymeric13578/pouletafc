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
        if (Schema::hasTable('user')) {
            return;
        }

        Schema::create('user', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->string('country');
            $table->string('phone');
            $table->string('email');
            $table->string('country_code');
            $table->string('ref');
            $table->string('role');
            $table->string('city');
            $table->string('confirmation_code');
            $table->string('status');
            $table->string('recoveryPass_code');
            $table->string('sexe');
            $table->string('id_father');
            $table->string('id_country');
            $table->string('photo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
