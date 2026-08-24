<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les raisons d'une mauvaise note.
 *
 * « note » stockait déjà l'appréciation et un commentaire libre, mais un
 * commentaire libre n'est pas exploitable en masse. Pour les appréciations
 * basses (verybad/bad/average), le client peut désormais cocher une ou
 * plusieurs raisons prédéfinies (NoteController::MOTIFS_NOTATION), stockées
 * ici. Nullable et jamais renseigné pour une bonne appréciation : ce n'est
 * pas une donnée obligatoire, seulement un signal en plus quand la note est
 * mauvaise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('note') && ! Schema::hasColumn('note', 'reasons')) {
            Schema::table('note', function (Blueprint $table) {
                $table->json('reasons')->nullable()->after('comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('note') && Schema::hasColumn('note', 'reasons')) {
            Schema::table('note', function (Blueprint $table) {
                $table->dropColumn('reasons');
            });
        }
    }
};
