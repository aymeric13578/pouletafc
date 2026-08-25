<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Remises que le marchand définit lui-même sur un produit de sa boutique,
 | depuis l'espace « Ma boutique » de l'application (voir MaBoutiqueController).
 |
 | Même convention de statut que les produits créés depuis ce même espace
 | (saveMyShopProduct) : une promotion créée par le marchand attend 'pending'
 | par défaut, et ne devient effective qu'une fois passée à 'Success' — la
 | même validation d'équipe que pour un nouveau produit, pour la même raison :
 | un marchand ne doit pas pouvoir annoncer une remise non vérifiée aux
 | clients sans qu'personne ne l'ait vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions')) {
            return;
        }

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_shop');
            $table->unsignedBigInteger('id_product');
            $table->string('title');
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('id_shop');
            $table->index('id_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
