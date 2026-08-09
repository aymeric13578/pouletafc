<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur les colonnes qui portent les filtres des cartes.
 *
 * clando et order_details n'étaient indexées que sur leur clé primaire et sur
 * id_agent. Or tout ce que les écrans filtrent passe par status — « en cours »,
 * « sans agent », « du jour » — et par created_at pour les compteurs de la
 * journée. Chaque rafraîchissement déclenchait donc un parcours complet de la
 * table, quatre fois par minute et par écran ouvert.
 *
 * Sans effet visible aujourd'hui (231 commandes, 27 courses), mais ces tables ne
 * font que grossir et les cartes interrogent en boucle : c'est le moment de
 * poser les index, pas quand l'écran commencera à ramer.
 *
 * Les index sont créés un par un et seulement s'ils manquent : la migration doit
 * pouvoir se rejouer sur une production où certains existeraient déjà.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [nom d'index => colonne] */
    private const INDEX = [
        'clando' => [
            'clando_status_index' => 'status',
            'clando_created_at_index' => 'created_at',
        ],
        'order_details' => [
            'order_details_status_index' => 'status',
            'order_details_created_at_index' => 'created_at',
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEX as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($index as $nom => $colonne) {
                if ($this->existe($table, $nom) || ! Schema::hasColumn($table, $colonne)) {
                    continue;
                }

                DB::statement("CREATE INDEX `{$nom}` ON `{$table}` (`{$colonne}`)");
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEX as $table => $index) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($index) as $nom) {
                if ($this->existe($table, $nom)) {
                    DB::statement("DROP INDEX `{$nom}` ON `{$table}`");
                }
            }
        }
    }

    private function existe(string $table, string $nom): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($index) => $index->Key_name === $nom);
    }
};
