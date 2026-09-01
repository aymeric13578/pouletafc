<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode de versement d'une demande de retrait, et numéro qui reçoit le dépôt.
 *
 * Jusqu'ici une demande ne portait qu'un montant (le solde entier, imposé) :
 * l'opérateur qui traitait la demande au tableau de bord devait rappeler
 * l'agent pour savoir comment le payer. L'agent choisit désormais lui-même
 * dans l'app (espèces ou Orange Money) et, pour Orange Money, saisit le
 * numéro à créditer — qui n'est pas forcément le sien.
 *
 * `cash` par défaut : c'est ce que valaient implicitement toutes les
 * demandes déjà enregistrées avant cette colonne (un humain rappelait et
 * remettait l'argent de la main à la main).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawal_requests')) {
            return;
        }

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawal_requests', 'mode')) {
                $table->string('mode', 10)->default('cash')->after('amount');
            }
            if (! Schema::hasColumn('withdrawal_requests', 'phone')) {
                $table->string('phone', 20)->nullable()->after('mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('withdrawal_requests')) {
            return;
        }

        Schema::table('withdrawal_requests', function (Blueprint $table) {
            if (Schema::hasColumn('withdrawal_requests', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('withdrawal_requests', 'mode')) {
                $table->dropColumn('mode');
            }
        });
    }
};
