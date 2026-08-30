<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nom du destinataire d'une course de coursier.
 *
 * Il était jusqu'ici noyé dans le texte libre `note`, sous la forme
 * « Expéditeur: () | Destinataire: Alphonse | Instructions: ... », que
 * l'application cliente compose et que personne ne peut relire proprement :
 * ni le mur des commandes, ni l'application agent ne savaient extraire à qui
 * remettre le colis, et la partie « Expéditeur » y arrivait toujours vide
 * puisque ces deux champs n'existent sur aucun écran.
 *
 * Une colonne propre, nullable : les courses déjà enregistrées n'en portent
 * pas, et l'affichage retombe alors sur la note comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_details') && ! Schema::hasColumn('order_details', 'name_customer')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->string('name_customer')->nullable()->after('phone_customer');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'name_customer')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('name_customer');
            });
        }
    }
};
