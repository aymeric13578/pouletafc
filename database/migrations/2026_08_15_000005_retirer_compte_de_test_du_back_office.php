<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire l'accès au tableau de bord du compte de test « test@example.com ».
 *
 * Ce compte porte le rôle employee_afc depuis le 28 juillet : il entre donc
 * dans le back-office. Une adresse en example.com n'appartient à personne — le
 * domaine est réservé à la documentation — et un compte que nul ne réclame ne
 * doit pas pouvoir ouvrir la gestion des commandes.
 *
 * Il est rétrogradé, non supprimé : il porte deux commandes, une course clando
 * et deux paniers. Effacer la ligne laisserait ces enregistrements sans auteur
 * et fausserait les comptes de la période.
 *
 * Ses droits de menu sont retirés avec le rôle : les laisser lui rendrait ses
 * accès si quelqu'un le renommait employé plus tard, sans l'avoir décidé.
 */
return new class extends Migration
{
    private const ADRESSE = 'test@example.com';

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $compte = DB::table('users')->where('email', self::ADRESSE)->first();

        if (! $compte) {
            return;
        }

        if (Schema::hasTable('menu_permissions')) {
            DB::table('menu_permissions')->where('user_id', $compte->id)->delete();
        }

        // Rétrogradé seulement s'il est encore interne : si quelqu'un l'a déjà
        // corrigé à la main, on ne repasse pas derrière lui.
        DB::table('users')
            ->where('id', $compte->id)
            ->whereIn('role', ['admin', 'employee_afc'])
            ->update(['role' => 'user', 'updated_at' => now()]);
    }

    public function down(): void
    {
        /*
         | Volontairement sans retour en arrière.
         |
         | Rendre l'accès au tableau de bord à un compte de test serait le
         | contraire de ce qu'on cherche. S'il s'avère qu'une personne réelle se
         | cache derrière cette adresse, l'écran « Droits d'accès » la renomme
         | employé en un clic — c'est une décision qui se prend, pas qui se
         | rejoue.
         */
    }
};
