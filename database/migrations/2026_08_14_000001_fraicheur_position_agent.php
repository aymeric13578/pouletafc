<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horodatage de la position d'un agent.
 *
 * La position n'était écrite qu'une fois, au démarrage de la journée par
 * takeDay, et plus jamais ensuite hors d'une course. Rien ne permettait de
 * distinguer un point relevé il y a dix secondes d'un point relevé il y a six
 * semaines — et les cartes présentaient les deux de la même façon.
 *
 * En base au moment d'écrire cette migration : trois agents « en service »
 * dont les positions dataient du 4 juillet, du 5 juillet et du 5 août.
 *
 * updated_at ne pouvait pas servir : il bouge à chaque modification du compte,
 * quelle qu'elle soit, et ne dit donc rien de la position.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'position_updated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('position_updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'position_updated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('position_updated_at');
            });
        }
    }
};
