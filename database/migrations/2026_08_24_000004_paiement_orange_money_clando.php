<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le paiement Orange Money (OrangePhaseUser / verifiedOrangePaymentStatus)
 * n'a jamais su marquer autre chose qu'une commande boutique payée :
 * verifiedOrangePaymentStatus écrit toujours dans order_details, quel que
 * soit l'id transmis. Un paiement OM pour une course Clando aboutissait donc
 * à un prélèvement réel (la ligne payments passe bien à 'Success') sans que
 * la course elle-même ne soit jamais marquée payée côté serveur — clando
 * n'a d'ailleurs aucune colonne pour le retenir.
 *
 * Deux ajouts, tous deux défensifs (ni `clando` ni `payments` n'ont de
 * migration de création suivie dans ce dépôt) :
 *  - clando.status_paiement : même rôle que order_details.status_paiement,
 *    pour qu'une course puisse être marquée payée.
 *  - payments.order_type : quelle table cible la mise à jour de statut
 *    (order_details par défaut, pour ne rien changer au comportement
 *    existant des commandes boutique et des courses coursier déjà en
 *    production ; 'clando' pour les nouveaux paiements de course moto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clando') && ! Schema::hasColumn('clando', 'status_paiement')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->string('status_paiement')->nullable();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'order_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('order_type')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clando') && Schema::hasColumn('clando', 'status_paiement')) {
            Schema::table('clando', function (Blueprint $table) {
                $table->dropColumn('status_paiement');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'order_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('order_type');
            });
        }
    }
};
