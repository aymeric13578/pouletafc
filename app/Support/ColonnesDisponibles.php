<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Une colonne existe-t-elle réellement dans la base servie ?
 *
 * La production porte des tables créées à la main, que les migrations ignoraient
 * : le schéma y a longtemps divergé du code sans que rien ne le signale. Écrire
 * dans une colonne absente fait échouer l'opération entière — une commande qu'on
 * ne peut plus annuler parce qu'on ne sait pas où ranger son motif.
 *
 * La réponse est mise en cache pour la durée de la requête : l'interroger à
 * chaque écriture coûterait une requête au schéma par ligne traitée.
 */
class ColonnesDisponibles
{
    /** @var array<string, bool> */
    private static array $connues = [];

    public static function existe(string $table, string $colonne): bool
    {
        $cle = $table . '.' . $colonne;

        return self::$connues[$cle] ??= Schema::hasColumn($table, $colonne);
    }

    /**
     * Vide le cache. Utile aux tests, qui modifient le schéma en cours d'exécution.
     */
    public static function oublier(): void
    {
        self::$connues = [];
    }
}
