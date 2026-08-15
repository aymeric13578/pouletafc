<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Droits d'accès aux menus du tableau de bord.
 *
 * Jusqu'ici, entrer dans le back-office donnait tout : un employé chargé des
 * commandes voyait la configuration des commissions et pouvait supprimer un
 * produit. Masquer un lien n'y changeait rien, l'URL restant devinable.
 *
 * Une ligne par menu accordé plutôt qu'une colonne de rôles figés : les
 * responsabilités d'un employé changent, et on ne veut pas ajouter un rôle
 * chaque fois qu'une combinaison nouvelle apparaît.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_permissions')) {
            return;
        }

        Schema::create('menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // Le nom de la route du menu : c'est ce qui permet de vérifier un
            // droit à partir de la page demandée, sans correspondance à tenir.
            $table->string('menu');
            $table->timestamps();

            // Un même menu ne s'accorde qu'une fois : sans cette contrainte, un
            // double clic créerait deux lignes et le retrait n'en enlèverait
            // qu'une.
            $table->unique(['user_id', 'menu']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_permissions');
    }
};
