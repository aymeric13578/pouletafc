<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le motif d'une annulation, et qui l'a prononcée.
 *
 * Une commande annulée passait en « failed » sans un mot : impossible de savoir
 * ensuite si le client s'était ravisé, si le produit manquait, si l'adresse
 * était introuvable ou si aucun agent n'avait répondu. Ce sont pourtant quatre
 * problèmes différents, et seul le dernier se corrige en recrutant.
 *
 * Trois colonnes plutôt qu'une : le motif ne dit rien s'il n'est pas daté et
 * attribué. « Client injoignable » posé par le comptoir et le même texte posé
 * par l'agent ne racontent pas la même histoire.
 */
return new class extends Migration
{
    // La table des courses s'appelle « clando » au singulier : c'est le nom que
    // porte le modele, et celui de la production.
    private const TABLES = ['order_details', 'clando'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'cancel_reason')) {
                    $blueprint->string('cancel_reason', 255)->nullable();
                }

                if (! Schema::hasColumn($table, 'cancelled_at')) {
                    $blueprint->timestamp('cancelled_at')->nullable();
                }

                /*
                | « client », « agent » ou « admin ». Une chaîne plutôt qu'un
                | enum : la production porte des tables créées à la main, et un
                | enum s'y modifie mal.
                */
                if (! Schema::hasColumn($table, 'cancelled_by')) {
                    $blueprint->string('cancelled_by', 30)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (['cancel_reason', 'cancelled_at', 'cancelled_by'] as $colonne) {
                    if (Schema::hasColumn($table, $colonne)) {
                        $blueprint->dropColumn($colonne);
                    }
                }
            });
        }
    }
};
