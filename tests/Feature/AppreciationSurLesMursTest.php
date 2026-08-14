<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\order_detail;
use App\Models\User;
use Tests\TestCase;

/**
 * Appréciations sur les murs publics.
 *
 * Elles dormaient en base sans être lisibles nulle part : le comptoir ne
 * pouvait pas savoir qu'une livraison s'était mal passée avant que le client
 * rappelle.
 */
class AppreciationSurLesMursTest extends TestCase
{
    public function test_le_mur_des_commandes_porte_l_appreciation_du_client(): void
    {
        $commande = order_detail::orderByDesc('id')->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        Note::where('id_order', $commande->id)->delete();

        $note = Note::create([
            'id_user' => $commande->id_user ?? User::value('id'),
            'id_agent' => $commande->id_agent ?? User::value('id'),
            'id_order' => $commande->id,
            'note' => 'bad',
            'comment' => 'Livraison très en retard',
        ]);

        try {
            $ligne = collect($this->get('/commandes')->viewData('page')['props']['initial']['orders'] ?? [])
                ->firstWhere('id', $commande->id);

            if (! $ligne) {
                $this->markTestSkipped('La commande n\'est pas sur la page courante du mur.');
            }

            $this->assertNotNull($ligne['appreciation'], "L'appréciation doit voyager avec la commande.");
            $this->assertSame('bad', $ligne['appreciation']['note']);
            $this->assertSame('Livraison très en retard', $ligne['appreciation']['commentaire']);
            $this->assertNotEmpty($ligne['appreciation']['emoji']);
            $this->assertNotEmpty($ligne['appreciation']['libelle']);
        } finally {
            $note->delete();
        }
    }

    /** Une commande jamais notée ne doit rien inventer. */
    public function test_une_commande_sans_avis_ne_porte_pas_d_appreciation(): void
    {
        $commande = order_detail::orderByDesc('id')
            ->whereNotIn('id', Note::whereNotNull('id_order')->pluck('id_order'))
            ->first();

        if (! $commande) {
            $this->markTestSkipped('Toutes les commandes sont notées.');
        }

        $ligne = collect($this->get('/commandes')->viewData('page')['props']['initial']['orders'] ?? [])
            ->firstWhere('id', $commande->id);

        if (! $ligne) {
            $this->markTestSkipped('La commande n\'est pas sur la page courante du mur.');
        }

        $this->assertNull($ligne['appreciation']);
    }
}
