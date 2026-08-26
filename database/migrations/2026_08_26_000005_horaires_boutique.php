<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horaires d'ouverture hebdomadaires de la boutique.
 *
 * Jusqu'ici, l'app cliente affichait un badge « Ouvert »/« Fermé » basé sur
 * shops.status — qui n'a rien à voir avec des horaires : c'est le statut
 * d'approbation de la boutique par l'équipe, et getAllShops ne renvoie déjà
 * que des boutiques status='Success'. Le badge affichait donc « Ouvert »
 * pour absolument toutes les boutiques listées, en toutes circonstances.
 *
 * Un objet JSON, clé = jour ISO (1 lundi .. 7 dimanche), valeur
 * {closed: bool, opens_at: "HH:MM", closes_at: "HH:MM"} — voir
 * Shop::estOuverteMaintenant(). Nullable : une boutique qui n'a pas encore
 * renseigné ses horaires n'est pas bloquée pour autant (voir la même
 * méthode, qui traite l'absence d'horaire comme "toujours ouverte").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shops') && ! Schema::hasColumn('shops', 'opening_hours')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->json('opening_hours')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'opening_hours')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->dropColumn('opening_hours');
            });
        }
    }
};
