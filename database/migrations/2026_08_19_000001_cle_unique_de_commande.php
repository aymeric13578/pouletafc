<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La clé que l'application attache à une tentative de commande.
 *
 * Le serveur reconnaît déjà un doublon à son contenu — mêmes produits, même
 * client, dans les cinq minutes. C'est une heuristique, et elle a le défaut de
 * son genre : un client qui commande réellement deux fois la même chose en cinq
 * minutes n'obtient qu'une commande.
 *
 * Avec cette clé, il n'y a plus à deviner. L'application en fabrique une par
 * tentative et la renvoie à l'identique quand elle réessaie : deux envois
 * portant la même clé sont la même commande, deux clés différentes sont deux
 * commandes, quoi qu'elles contiennent.
 *
 * L'heuristique reste en place derrière, pour les versions déjà installées qui
 * n'envoient pas de clé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_details') || Schema::hasColumn('order_details', 'cle_unique')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            $table->string('cle_unique', 64)->nullable();

            /*
            | Index unique, et non simple : c'est la base qui doit garantir
            | l'unicité, pas le code. Deux requêtes arrivées en même temps
            | passeraient toutes deux le contrôle applicatif — seule la
            | contrainte les départage.
            */
            $table->unique('cle_unique', 'order_details_cle_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_details') || ! Schema::hasColumn('order_details', 'cle_unique')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropUnique('order_details_cle_unique');
            $table->dropColumn('cle_unique');
        });
    }
};
