<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 du plan « Circuits de l'argent » — deux ajouts :
 *
 * 1. `withdrawal_requests.acteur_type` : les marchands demandent leurs
 *    retraits par le même mécanisme que les agents (même formulaire côté
 *    app, même page de validation au tableau de bord, avec une colonne
 *    Type). 'agent' par défaut : toutes les demandes existantes en étaient.
 *    `id_agent` garde son nom mais porte l'id de l'acteur (id_user d'agent,
 *    ou id de boutique selon acteur_type).
 *
 * 2. `cart_items.majoration_unitaire` : la part de majoration boutique
 *    contenue dans le prix unitaire vendu, figée au moment de l'achat.
 *    Sans elle, la ventilation « net marchand / part société » d'une vente
 *    est irreconstituable après coup — le taux de majoration peut avoir
 *    changé depuis. Zéro par défaut : les lignes déjà en base comme les
 *    boutiques sans majoration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('withdrawal_requests') && ! Schema::hasColumn('withdrawal_requests', 'acteur_type')) {
            Schema::table('withdrawal_requests', function (Blueprint $table) {
                $table->string('acteur_type', 20)->default('agent')->after('id');
                $table->index('acteur_type');
            });
        }

        if (Schema::hasTable('cart_items') && ! Schema::hasColumn('cart_items', 'majoration_unitaire')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->decimal('majoration_unitaire', 12, 2)->default(0)->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('withdrawal_requests') && Schema::hasColumn('withdrawal_requests', 'acteur_type')) {
            Schema::table('withdrawal_requests', function (Blueprint $table) {
                $table->dropColumn('acteur_type');
            });
        }
        if (Schema::hasTable('cart_items') && Schema::hasColumn('cart_items', 'majoration_unitaire')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropColumn('majoration_unitaire');
            });
        }
    }
};
