<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Parameter;
use App\Models\User;
use App\Support\NotationAgent;
use Tests\TestCase;

/**
 * Écran des notes et commentaires.
 *
 * Les appréciations existaient en base et n'étaient lisibles nulle part :
 * l'application agent affichait un score, personne ne pouvait voir ce qui le
 * composait ni ce que les clients avaient écrit.
 */
class PageNotesTest extends TestCase
{
    private const URL = '/dashboard/notes';

    private function staff(): User
    {
        $staff = User::first();

        if (! $staff) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }

        $staff->role = 'admin';
        $staff->save();

        return $staff;
    }

    protected function setUp(): void
    {
        parent::setUp();
        NotationAgent::oublierBareme();
    }

    public function test_l_espace_est_reserve_a_l_equipe(): void
    {
        $visiteur = User::first();

        if (! $visiteur) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }

        // « user » est le rôle des clients : la colonne est une énumération, et
        // lui écrire une valeur inventée échouerait pour une raison sans rapport
        // avec ce que ce test vérifie.
        $role = $visiteur->role;
        $visiteur->role = 'user';
        $visiteur->save();

        try {
            $this->actingAs($visiteur)->get(self::URL)->assertForbidden();
        } finally {
            $visiteur->role = $role;
            $visiteur->save();
        }
    }

    /** Un visiteur non connecté est renvoyé vers la connexion, pas servi. */
    public function test_un_visiteur_non_connecte_n_entre_pas(): void
    {
        $this->get(self::URL)->assertRedirect();
    }

    public function test_la_page_affiche_le_bareme_le_classement_et_les_avis(): void
    {
        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSeeText('Barème de notation');
        $reponse->assertSeeText('Classement des agents');
        $reponse->assertSeeText('Appréciations récentes');

        // Les cinq appréciations doivent être réglables, la pire comprise.
        foreach (Parameter::APPRECIATIONS as $appreciation) {
            $reponse->assertSee('points.' . $appreciation, false);
        }

        // Et les trois genres de prestation filtrables.
        foreach (['Commande', 'Course', 'Clando'] as $prestation) {
            $reponse->assertSeeText($prestation);
        }
    }

    /*
     | Le mieux noté doit être celui qui sert le mieux, pas celui qui sert le
     | plus : le classement se fait sur la note sur 5, pas sur le total.
     */
    public function test_le_mieux_note_apparait_en_tete(): void
    {
        $laborieux = 970001;
        $irreprochable = 970002;

        Note::whereIn('id_agent', [$laborieux, $irreprochable])->delete();

        // Barème posé explicitement : le classement se juge sur des points
        // connus, pas sur ceux qu'un autre test aurait laissés en place.
        $grille = Parameter::active();

        if (! $grille) {
            $this->markTestSkipped('Aucune configuration active.');
        }

        $initial = $grille->pointsDeNotation();
        $grille->update([
            'note_points_verybad' => -2,
            'note_points_bad' => -1,
            'note_points_average' => 1,
            'note_points_good' => 1.5,
            'note_points_excellent' => 2,
        ]);
        NotationAgent::oublierBareme();

        try {
            foreach (range(1, 40) as $i) {
                Note::create(['id_user' => 1, 'id_agent' => $laborieux, 'id_order' => 800000 + $i, 'note' => 'average']);
            }

            foreach (range(1, 5) as $i) {
                Note::create(['id_user' => 1, 'id_agent' => $irreprochable, 'id_order' => 810000 + $i, 'note' => 'excellent']);
            }

            $classement = collect(NotationAgent::pourAgents([$laborieux, $irreprochable]));

            $this->assertGreaterThan(
                $classement[$laborieux]['sur_cinq'],
                $classement[$irreprochable]['sur_cinq'],
                'Cinq excellentes prestations valent mieux que quarante moyennes.'
            );

            $this->assertGreaterThan(
                $classement[$irreprochable]['total'],
                $classement[$laborieux]['total'],
                'Le total, lui, suit le volume — raison pour laquelle il ne classe pas.'
            );
        } finally {
            Note::whereIn('id_agent', [$laborieux, $irreprochable])->delete();
            $grille->update(
                collect($initial)->mapWithKeys(fn ($p, $c) => ['note_points_' . $c => $p])->toArray()
            );
            NotationAgent::oublierBareme();
        }
    }
}
