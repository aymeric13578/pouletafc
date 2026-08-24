<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de la note affichée et du score de distribution.
 *
 * Deux familles de constantes qui n'ont rien à faire codées en dur : la
 * fenêtre/crédibilité de la note lissée (demandées explicitement réglables),
 * et les paramètres du score de distribution (vitesse supposée, rayon,
 * cadence des vagues) — sans quoi corriger l'un de ces nombres exigerait un
 * déploiement.
 */
return new class extends Migration
{
    private const DEFAUTS = [
        'note_fenetre_recente' => 150,
        'note_credibilite_c' => 20,
        'distribution_vitesse_kmh' => 25.0,
        'distribution_rayon_km' => 8.0,
        'distribution_delai_vague_s' => 10,
        'distribution_taille_vague' => 3,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        Schema::table('parameters', function (Blueprint $table) {
            if (! Schema::hasColumn('parameters', 'note_fenetre_recente')) {
                $table->unsignedInteger('note_fenetre_recente')->default(150)->after('note_points_excellent');
            }
            if (! Schema::hasColumn('parameters', 'note_credibilite_c')) {
                $table->unsignedInteger('note_credibilite_c')->default(20)->after('note_fenetre_recente');
            }
            if (! Schema::hasColumn('parameters', 'distribution_vitesse_kmh')) {
                $table->decimal('distribution_vitesse_kmh', 5, 1)->default(25.0)->after('note_credibilite_c');
            }
            if (! Schema::hasColumn('parameters', 'distribution_rayon_km')) {
                $table->decimal('distribution_rayon_km', 5, 1)->default(8.0)->after('distribution_vitesse_kmh');
            }
            if (! Schema::hasColumn('parameters', 'distribution_delai_vague_s')) {
                $table->unsignedInteger('distribution_delai_vague_s')->default(10)->after('distribution_rayon_km');
            }
            if (! Schema::hasColumn('parameters', 'distribution_taille_vague')) {
                $table->unsignedInteger('distribution_taille_vague')->default(3)->after('distribution_delai_vague_s');
            }
        });

        // Les lignes déjà en base n'ont pas de valeur par défaut appliquée
        // rétroactivement par MySQL : on la pousse explicitement, sans quoi
        // une grille existante donnerait des scores nuls ou aberrants.
        foreach (self::DEFAUTS as $colonne => $defaut) {
            if (Schema::hasColumn('parameters', $colonne)) {
                DB::table('parameters')->whereNull($colonne)->update([$colonne => $defaut]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('parameters')) {
            return;
        }

        Schema::table('parameters', function (Blueprint $table) {
            foreach (array_keys(self::DEFAUTS) as $colonne) {
                if (Schema::hasColumn('parameters', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
