<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Remplace l'ancien domaine dans toutes les adresses stockées en base.
 *
 * Les images de catégories, sous-catégories, produits et boutiques étaient
 * enregistrées avec leur adresse complète, préfixée en dur par
 * pouletafc.2gether-network.com. Ce domaine ne résout plus du tout : chaque
 * fiche créée avant la correction pointe donc vers un hôte inexistant, et son
 * image ne s'affiche nulle part — ni dans l'application cliente, ni dans les
 * courriels.
 *
 * Les contrôleurs n'écrivent plus cette adresse, mais les lignes déjà en base
 * restent à réparer. Plutôt que d'énumérer des tables — la production en compte
 * que les migrations ignorent, créées à la main — on parcourt toutes les
 * colonnes textuelles et on ne touche qu'aux valeurs qui contiennent
 * effectivement le domaine mort. Une valeur qui le contient est fausse dans
 * tous les cas.
 *
 * Rejouable sans risque : après passage, plus aucune ligne ne correspond.
 */
return new class extends Migration
{
    private const ANCIEN = 'pouletafc.2gether-network.com';

    private const NOUVEAU = 'pouletafc.com';

    public function up(): void
    {
        $base = DB::connection()->getDatabaseName();
        $corrigees = 0;

        foreach ($this->colonnesTextuelles($base) as $colonne) {
            $table = $colonne->TABLE_NAME;
            $champ = $colonne->COLUMN_NAME;

            try {
                $corrigees += DB::table($table)
                    ->where($champ, 'like', '%' . self::ANCIEN . '%')
                    ->update([
                        $champ => DB::raw(
                            'REPLACE(`' . $champ . '`, ' . DB::getPdo()->quote(self::ANCIEN) . ', ' . DB::getPdo()->quote(self::NOUVEAU) . ')'
                        ),
                    ]);
            } catch (\Throwable $e) {
                // Une colonne en lecture seule ou une vue ne doit pas interrompre
                // la réparation des autres.
                Log::warning("Remplacement du domaine impossible sur $table.$champ", [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Ancien domaine remplacé dans $corrigees valeurs.");
    }

    /**
     * Les colonnes susceptibles de contenir une adresse, dans la base courante.
     */
    private function colonnesTextuelles(string $base): array
    {
        try {
            return DB::select(
                'SELECT c.TABLE_NAME, c.COLUMN_NAME
                   FROM information_schema.COLUMNS c
                   JOIN information_schema.TABLES t
                     ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                  WHERE c.TABLE_SCHEMA = ?
                    AND t.TABLE_TYPE = ?
                    AND c.DATA_TYPE IN (?, ?, ?, ?, ?)',
                [$base, 'BASE TABLE', 'varchar', 'char', 'text', 'mediumtext', 'longtext']
            );
        } catch (\Throwable $e) {
            // SQLite en développement et dans les tests ne connaît pas
            // information_schema : la réparation n'y a de toute façon rien à faire.
            Log::info('Colonnes non inspectables, remplacement du domaine ignoré.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function down(): void
    {
        // Restaurer un domaine qui n'existe plus n'aurait aucun sens.
    }
};
