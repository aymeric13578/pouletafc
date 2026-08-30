<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que l'entreprise perçoit sur une boutique hébergée.
 *
 * Deux modèles, exclusifs l'un de l'autre pour une même boutique :
 *
 *  - commission : les prix des produits de la boutique sont majorés d'un
 *    pourcentage à l'affichage côté client, et cette majoration revient à
 *    l'entreprise à l'achat. Le marchand, lui, continue de saisir et de voir
 *    son prix de base — la majoration lui est invisible.
 *  - abonnement : aucune majoration, un montant dû périodiquement. Le montant
 *    est propre à chaque boutique, il ne s'agit pas d'un forfait unique.
 *
 * La portée d'une commission est soit la boutique entière, soit une sélection
 * de produits (table `boutique_commission_produits`), chacun pouvant porter
 * son propre taux — une boutique peut vouloir majorer ses plats mais pas ses
 * boissons.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boutique_facturations')) {
            Schema::create('boutique_facturations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

                // 'commission' | 'abonnement'
                $table->string('mode', 20)->default('commission');
                // 'boutique' | 'produits' — n'a de sens qu'en mode commission.
                $table->string('portee', 20)->default('boutique');

                // Pourcentage ajouté au prix affiché au client.
                $table->decimal('taux', 5, 2)->nullable();

                /*
                 | Abonnement : montant propre à la boutique, sa périodicité et
                 | la prochaine échéance. La notification « il reste N jours »
                 | affichée au marchand se calcule à partir de cette date, pas
                 | d'un champ figé qu'il faudrait tenir à jour.
                 */
                $table->integer('abonnement_montant')->nullable();
                $table->string('abonnement_periodicite', 20)->nullable();
                $table->date('abonnement_echeance')->nullable();

                $table->boolean('actif')->default(true);
                $table->timestamps();

                // Une seule facturation par boutique : deux lignes actives
                // laisseraient le calcul du prix choisir arbitrairement.
                $table->unique('shop_id');
            });
        }

        if (! Schema::hasTable('boutique_commission_produits')) {
            Schema::create('boutique_commission_produits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('boutique_facturation_id')
                    ->constrained('boutique_facturations')
                    ->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                // Taux propre à ce produit ; à défaut, celui de la boutique.
                $table->decimal('taux', 5, 2)->nullable();
                $table->timestamps();

                $table->unique(['boutique_facturation_id', 'product_id'], 'bcp_facturation_produit_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boutique_commission_produits');
        Schema::dropIfExists('boutique_facturations');
    }
};
