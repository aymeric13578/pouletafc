<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements et enregistrements audio déclenchés depuis l'écran de course
 * (clando.dart, boutons "Enregistrer"/"Signaler").
 *
 * Deux usages du même bouton "panique" côté client :
 *
 *  - signalement : déclenche une alerte immédiate sur les écrans "mur" et
 *    carte du tableau de bord (voir ClandoBoardController::feed/OrderMapController::feed),
 *    sans fichier associé ;
 *  - enregistrement : capture audio de la course, uploadée à la fin, sans
 *    déclencher d'alerte à elle seule — elle sert de preuve a posteriori,
 *    consultée depuis la page Sécurité du tableau de bord.
 *
 * Le fichier audio ne vit jamais dans public_path('upload') : ce dossier est
 * servi sans la moindre authentification (routes v1.0, CLAUDE.md règle 8),
 * et un enregistrement de course est justement le genre de contenu qui ne
 * doit être consultable que par l'équipe, depuis le tableau de bord.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents_securite', function (Blueprint $table) {
            $table->id();

            // 'clando' uniquement pour l'instant (bouton présent sur cet
            // écran-là) ; id_order nullable pour couvrir une commande le
            // jour où le même bouton y apparaît, sans nouvelle migration.
            $table->string('type_course', 20)->default('clando');
            $table->unsignedBigInteger('id_clando')->nullable();
            $table->unsignedBigInteger('id_order')->nullable();

            $table->unsignedBigInteger('id_client')->nullable();
            $table->unsignedBigInteger('id_agent')->nullable();

            // 'signalement' | 'enregistrement'
            $table->string('type', 20);

            // Chemin relatif sous storage/app/incidents-securite, jamais une
            // URL publique — voir App\Http\Controllers\Admin\IncidentSecuriteController.
            $table->string('audio_path')->nullable();

            // 'nouveau' | 'vu' | 'traite' — suivi par l'équipe depuis la page
            // Sécurité, pour distinguer une alerte encore active d'une
            // déjà prise en charge.
            $table->string('statut', 20)->default('nouveau');

            $table->timestamps();

            $table->index(['type', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents_securite');
    }
};
