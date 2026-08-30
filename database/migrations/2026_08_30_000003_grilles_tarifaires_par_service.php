<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grilles tarifaires par service, avec plages horaires.
 *
 * La table `parameters` mélangeait les trois services dans une seule ligne
 * plate : prix au km clando ET commande, minimum clando ET commande, quatre
 * pourcentages de commission côte à côte. Ajouter un service imposait
 * d'ajouter deux colonnes de plus, et rien ne permettait de facturer
 * différemment selon l'heure — un même tarif s'appliquait à 7h du matin et
 * à minuit.
 *
 * Deux tables : une grille par service (`tarifs`), et ses plages horaires
 * (`tarif_plages`), 2 à 5 par jour, qui s'appliquent automatiquement selon
 * l'heure courante. Chaque plage porte son propre plancher, plafond, prix au
 * kilomètre et commissions.
 *
 * `parameters` n'est pas supprimée : ClandoController, OrderController et
 * l'endpoint getParameters — consommé par les trois applications mobiles —
 * la lisent encore (CLAUDE.md règle 1). Les deux coexistent, la grille
 * l'emportant quand elle en porte une pour le service concerné.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tarifs')) {
            Schema::create('tarifs', function (Blueprint $table) {
                $table->id();
                /*
                 | Le service facturé. Volontairement une chaîne et non un enum
                 | MySQL : ajouter un service à un enum demande un ALTER TABLE
                 | sur une table verrouillée en production, et c'est exactement
                 | la rigidité qui a conduit à empiler les colonnes dans
                 | `parameters`.
                 */
                $table->string('service', 30);
                $table->string('libelle')->nullable();
                // 'Success' = grille appliquée, 'pending' = historique, mêmes
                // valeurs que parameters.status pour ne pas inventer un
                // troisième vocabulaire de statut (CLAUDE.md règle 3).
                $table->string('status', 20)->default('pending');
                $table->timestamps();

                $table->index(['service', 'status']);
            });
        }

        if (! Schema::hasTable('tarif_plages')) {
            Schema::create('tarif_plages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tarif_id')->constrained('tarifs')->cascadeOnDelete();

                /*
                 | Bornes de la plage, en heure du Cameroun. `fin` peut être
                 | antérieure à `debut` : une plage de nuit (18:00 → 06:00)
                 | franchit minuit, et c'est le cas d'usage principal d'une
                 | majoration horaire. Voir TarifPlage::couvre().
                 */
                $table->time('debut');
                $table->time('fin');

                // Plancher et plafond du prix calculé. Le plafond est ce qui
                // manquait : rien ne bornait une course de 40 km.
                $table->integer('prix_min')->default(0);
                $table->integer('prix_max')->nullable();
                $table->integer('prix_km')->default(0);

                /*
                 | Ce que l'entreprise retient sur la course, en pourcentage.
                 | Deux taux pour le clando, qui distingue une course classique
                 | d'une course VIP ; les autres services n'utilisent que le
                 | premier. Pour une livraison, la commission porte sur les
                 | seuls frais de livraison, jamais sur le panier.
                 */
                $table->decimal('commission', 5, 2)->default(0);
                $table->decimal('commission_vip', 5, 2)->nullable();
                // Majoration appliquée au prix d'une course VIP.
                $table->decimal('majoration_vip', 5, 2)->nullable();

                $table->unsignedSmallInteger('ordre')->default(0);
                $table->timestamps();

                $table->index(['tarif_id', 'ordre']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_plages');
        Schema::dropIfExists('tarifs');
    }
};
