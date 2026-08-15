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
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
