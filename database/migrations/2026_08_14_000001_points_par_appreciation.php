<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barème de notation configurable.
 *
 * Les points de chaque appréciation étaient écrits en dur dans NoteController
 * (-2, -1, 1, 1.5, 2). Personne ne pouvait les corriger sans toucher au code, et
 * rien n'expliquait pourquoi une mauvaise note retirait deux points quand une
 * excellente n'en donnait que deux. Le barème vit désormais avec le reste de la
 * configuration, sur la ligne active.
 *
 * Les valeurs par défaut reprennent exactement l'ancien barème : les scores
 * affichés le lendemain de cette migration doivent être ceux de la veille, sans
 * quoi la mise à jour ressemblerait à une remise à zéro des agents.
 */
return new class extends Migration
{
    /** Ancien barème codé en dur, repris tel quel. */
    private const DEFAUTS = [
        'note_points_verybad' => -2,
        'note_points_bad' => -1,
        'note_points_average' => 1,
        'note_points_good' => 1.5,
        'note_points_excellent' => 2,
    ];

    public function up(): void
    {
        if (Schema::hasTable('parameters')) {
            Schema::table('parameters', function (Blueprint $table) {
                foreach (self::DEFAUTS as $colonne => $defaut) {
                    if (! Schema::hasColumn('parameters', $colonne)) {
                        $table->decimal($colonne, 5, 2)->default($defaut)->after('vip_percentage');
                    }
                }
            });

            // Les lignes déjà en base n'ont pas de valeur : sans ce coup de
            // pouce, une configuration existante donnerait des scores nuls.
            foreach (self::DEFAUTS as $colonne => $defaut) {
                if (Schema::hasColumn('parameters', $colonne)) {
                    DB::table('parameters')->whereNull($colonne)->update([$colonne => $defaut]);
                }
            }
        }

        /*
         | « verybad » manquait à l'enum.
         |
         | NoteController le comptait déjà dans ses résultats mais sa validation
         | le refusait : la pire appréciation était impossible à donner, et le
         | compteur restait à zéro pour tout le monde.
         */
        $this->elargirLesAppreciations();
    }

    /**
     * Ajoute « verybad » à la liste autorisée, et rien d'autre.
     *
     * La table s'appelle « note » au singulier : c'est le nom réel en base,
     * celui que porte le modèle. Viser « notes » aurait laissé l'enum inchangé
     * sans le moindre avertissement.
     *
     * L'opération est encadrée parce qu'elle porte sur une table qui contient
     * déjà les appréciations des clients. On regarde la définition en place
     * avant d'y toucher :
     *
     *  - si « verybad » y figure déjà, il n'y a rien à faire ;
     *  - si la colonne n'est pas un enum, on la laisse : elle accepte déjà tout,
     *    et la convertir risquerait de vider les valeurs qui n'entrent pas dans
     *    la nouvelle liste ;
     *  - la nullabilité d'origine est reconduite telle quelle, pour ne pas
     *    transformer une colonne tolérante en colonne obligatoire au passage.
     */
    private function elargirLesAppreciations(): void
    {
        if (! Schema::hasTable('note')) {
            return;
        }

        $colonne = collect(DB::select('SHOW COLUMNS FROM `note`'))
            ->firstWhere('Field', 'note');

        if (! $colonne) {
            return;
        }

        $definition = strtolower((string) $colonne->Type);

        if (! str_starts_with($definition, 'enum(') || str_contains($definition, "'verybad'")) {
            return;
        }

        // Les valeurs déjà autorisées, plus la nôtre : on ajoute sans retirer,
        // faute de quoi des appréciations existantes deviendraient illisibles.
        preg_match_all("/'([^']*)'/", $definition, $trouvees);
        $valeurs = collect($trouvees[1])->push('verybad')->unique()->values();

        $liste = $valeurs->map(fn ($v) => "'" . str_replace("'", "''", $v) . "'")->implode(',');
        $nullabilite = strtoupper((string) $colonne->Null) === 'YES' ? 'NULL' : 'NOT NULL';

        DB::statement("ALTER TABLE `note` MODIFY `note` ENUM($liste) $nullabilite");
    }

    public function down(): void
    {
        if (Schema::hasTable('parameters')) {
            Schema::table('parameters', function (Blueprint $table) {
                foreach (array_keys(self::DEFAUTS) as $colonne) {
                    if (Schema::hasColumn('parameters', $colonne)) {
                        $table->dropColumn($colonne);
                    }
                }
            });
        }
    }
};
