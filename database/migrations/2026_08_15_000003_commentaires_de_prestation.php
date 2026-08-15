<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commentaires laissés sur une prestation.
 *
 * Distincts des notes, et non un champ de plus sur elles. Une note est unique
 * par prestation — on ne l'attribue qu'une fois, et son commentaire est enfermé
 * avec elle. Or un client peut vouloir dire quelque chose sans juger, revenir
 * préciser un détail après coup, ou signaler un problème apparu à la
 * réception. Rien de tout cela ne tient dans le champ « comment » d'une note.
 *
 * D'où une table à part, où plusieurs commentaires peuvent viser la même
 * prestation, et où l'administration peut répondre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commentaires')) {
            return;
        }

        Schema::create('commentaires', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('id_user');
            // L'agent visé. Peut manquer : un client peut commenter une
            // commande que personne n'a encore prise.
            $table->unsignedBigInteger('id_agent')->nullable();

            // L'une des deux, jamais les deux : un commentaire porte sur une
            // prestation et une seule.
            $table->unsignedBigInteger('id_order')->nullable();
            $table->unsignedBigInteger('id_clando')->nullable();

            $table->text('contenu');

            /*
             | Réponse de l'administration.
             |
             | Sur la même ligne plutôt que dans un fil : un commentaire de
             | client appelle une réponse, pas une conversation. Un fil complet
             | demanderait des notifications et une modération qu'on n'a pas.
             */
            $table->text('reponse')->nullable();
            $table->timestamp('repondu_le')->nullable();

            /*
             | Un commentaire déplacé se masque, il ne se supprime pas : le
             | retirer effacerait la trace d'un incident que l'exploitation peut
             | avoir besoin de retrouver.
             */
            $table->boolean('masque')->default(false);

            $table->timestamps();

            $table->index('id_agent');
            $table->index('id_order');
            $table->index('id_clando');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentaires');
    }
};
