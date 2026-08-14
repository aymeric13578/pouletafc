<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note libre attachée à une commande.
 *
 * L'écran « commander une course » de l'application transmet un champ « note »
 * rassemblant l'expéditeur, le destinataire, les instructions de remise et le
 * commentaire du client. Aucune colonne ne pouvait l'accueillir : tout ce texte
 * était perdu, alors que c'est précisément ce dont le livreur a besoin pour
 * remettre un colis à la bonne personne.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_details') && ! Schema::hasColumn('order_details', 'note')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->text('note')->nullable()->after('address');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'note')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
