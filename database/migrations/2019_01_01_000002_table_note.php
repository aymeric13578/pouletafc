<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appréciations laissées par les clients après une prestation.
 *
 * Comme parameters, cette table n'existait qu'en production, créée à la main.
 * Elle porte deux identifiants d'utilisateur — celui qui note et celui qui est
 * noté — et se rattache soit à une commande, soit à une course clando.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('note')) {
            return;
        }

        Schema::create('note', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_agent');
            // L'une des deux est renseignée, jamais les deux : une appréciation
            // porte sur une prestation et une seule.
            $table->unsignedBigInteger('id_order')->nullable();
            $table->unsignedBigInteger('id_clando')->nullable();
            $table->enum('note', ['verybad', 'bad', 'average', 'good', 'excellent']);
            $table->text('comment')->nullable();
            $table->timestamps();

            // Le score d'un agent se lit par id_agent, et l'écran de notation
            // vérifie « ai-je déjà noté cette prestation ? » par les deux autres.
            $table->index('id_agent');
            $table->index('id_order');
            $table->index('id_clando');
        });
    }

    public function down(): void
    {
        // Voir la migration parameters : ne rien supprimer d'une table
        // préexistante en production.
    }
};
