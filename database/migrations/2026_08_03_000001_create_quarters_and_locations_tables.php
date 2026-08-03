<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les tables quarters et locations existent en production (créées hors
 * migrations) mais n'étaient décrites nulle part dans le dépôt : impossible de
 * monter un environnement neuf ou de travailler en local sur l'endpoint
 * getLocations.
 *
 * La création est conditionnée par Schema::hasTable : en production, où les
 * tables sont déjà là avec leurs données, cette migration ne fait rien.
 * Le schéma reproduit celui constaté en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quarters')) {
            Schema::create('quarters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->enum('status', ['pending', 'Success', 'failed']);
                $table->unsignedBigInteger('id_user');
            });
        }

        if (! Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_quarter');
                $table->string('name');
                $table->enum('status', ['pending', 'Success', 'failed']);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->unsignedBigInteger('id_user');
                // Stockées en varchar en production, on ne change pas le type
                // pour rester strictement identique à l'existant.
                $table->string('longitude');
                $table->string('latitude');
            });
        }
    }

    public function down(): void
    {
        // Pas de rollback : ces tables portent des données de production
        // antérieures à cette migration.
    }
};
