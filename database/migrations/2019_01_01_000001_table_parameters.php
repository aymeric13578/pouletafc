<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille de configuration : commissions, tarifs, barème de notation.
 *
 * La table vivait en production sans qu'aucune migration ne la décrive : elle y
 * avait été créée à la main. Conséquence, un dépôt fraîchement installé ne
 * pouvait ni la créer ni la tester, et le code qui s'appuie dessus n'était
 * vérifiable que sur le serveur — c'est-à-dire trop tard.
 *
 * Datée volontairement en 2019 pour passer avant les migrations qui complètent
 * cette table. Elle ne touche à rien là où la table existe déjà.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parameters')) {
            return;
        }

        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->integer('clando_kilometer')->default(0);
            $table->integer('command_kilometer')->default(0);
            $table->integer('min_price_clando')->default(0);
            $table->integer('min_price_command')->default(0);
            $table->integer('clando_agent_commission')->default(0);
            $table->integer('clando_agent_command')->default(0);
            $table->integer('vip_percentage')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Volontairement sans suppression : cette migration ne fait que décrire
        // après coup une table qui préexiste en production. Un rollback qui la
        // supprimerait détruirait la configuration en vigueur.
    }
};
