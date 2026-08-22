<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur les colonnes qui portent les mises à jour de position et de prise
 * en charge, restées non indexées après la migration du 09/08.
 *
 * clando.ref et order_details.ref sont filtrées à chaque mise à jour de
 * position d'un agent en course (updatePositionAgent / updatePositionAgentOrder,
 * toutes les ~3 secondes pendant une livraison) et à chaque prise de commande
 * (takeClandoCommand / takeOrderCommand). clando.id_agent et order_details.id_user
 * portent la reprise de course (SuiviController::getCourseEnCours) côté agent et
 * côté client. Aucune des quatre n'était indexée : chaque appel parcourait la
 * table entière pour trouver une seule ligne.
 *
 * Sans effet visible aujourd'hui (quelques dizaines de lignes), mais ce sont
 * précisément les requêtes les plus fréquentes de toute l'API — c'est elles qui
 * dégraderaient en premier si le nombre d'utilisateurs actifs augmentait.
 *
 * Les colonnes déjà indexées ailleurs (order_details.id_agent, clando.id_user,
 * status, created_at) ne sont pas reprises ici. Les colonnes de position générale
 * (users.actual_lat_position_agent/lon/position_updated_at) ne le sont pas non
 * plus : elles ne sont jamais filtrées ni triées en SQL, seulement lues après un
 * WHERE sur une autre colonne — les indexer n'accélérerait aucune requête et
 * coûterait une écriture supplémentaire à chaque relevé de position d'agent.
 *
 * Les index sont créés un par un et seulement s'ils manquent : la migration doit
 * pouvoir se rejouer sur une production où certains existeraient déjà.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [nom d'index => colonne] */
    private const INDEX = [
        'clando' => [
            'clando_ref_index' => 'ref',
            'clando_id_agent_index' => 'id_agent',
        ],
        'order_details' => [
            'order_details_ref_index' => 'ref',
            'order_details_id_user_index' => 'id_user',
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
