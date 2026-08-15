<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compléments : ce qu'on propose en plus d'un produit.
 *
 * Un complément n'est pas une entité à part : c'est un produit, avec son prix,
 * son stock et sa boutique. Une portion de frites se vend seule autant qu'elle
 * accompagne un poulet. En faire une table distincte aurait dédoublé tout ce
 * qui existe déjà — panier, commande, stock — pour la seule raison qu'on le
 * propose au moment d'en choisir un autre.
 *
 * D'où deux ajouts seulement : un drapeau sur le produit, et le lien qui dit
 * quel produit propose quel complément.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'is_complement')) {
            Schema::table('products', function (Blueprint $table) {
                /*
                 | Un complément reste vendable seul : ce drapeau ne le retire pas
                 | du catalogue, il indique seulement qu'il peut être proposé en
                 | accompagnement. Les écrans de vente s'en servent pour ne pas
                 | mélanger les deux listes.
                 */
                $table->boolean('is_complement')->default(false)->after('status');
            });
        }

        if (! Schema::hasTable('product_complement')) {
            Schema::create('product_complement', function (Blueprint $table) {
                $table->id();
                // Le produit principal, celui qu'on achète.
                $table->unsignedBigInteger('product_id');
                // Le complément qu'il propose — un produit lui aussi.
                $table->unsignedBigInteger('complement_id');
                $table->timestamps();

                /*
                 | Sans clés étrangères : la table des produits porte des lignes
                 | héritées et des suppressions logiques, et une contrainte ferait
                 | échouer des opérations qui passent aujourd'hui. Les liens
                 | orphelins sont écartés à la lecture.
                 */
                $table->unique(['product_id', 'complement_id']);
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_complement');

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'is_complement')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_complement');
            });
        }
    }
};
