<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Parameter;
use App\Support\NotationAgent;
use Tests\TestCase;

/**
 * Notation des agents.
 *
 * Le barème était écrit en dur dans le contrôleur et le calcul n'existait qu'à
 * un endroit : l'application agent. Le tableau de bord n'affichait aucun score,
 * et personne ne pouvait comparer deux agents.
 */
class NotationAgentTest extends TestCase
{
    private const AGENT = 90001;
    private const CLIENT = 90002;

    /**
     * Barème d'origine, remis en place à la fin.
     *
     * Ces tests modifient la grille active. Sans restauration, ils laissaient
     * derrière eux un barème inventé — et le test suivant, qui supposait qu'une
     * appréciation moyenne rapporte des points, échouait pour une raison
     * n'ayant rien à voir avec ce qu'il vérifiait.
     *
     * @var array<string, float>|null
     */
    private ?array $baremeInitial = null;

    protected function setUp(): void
    {
        parent::setUp();

        Note::where('id_agent', self::AGENT)->delete();
        NotationAgent::oublierBareme();
        $this->baremeInitial = Parameter::active()?->pointsDeNotation();
    }

    protected function tearDown(): void
    {
        Note::where('id_agent', self::AGENT)->delete();

        if ($this->baremeInitial) {
            Parameter::active()?->update(
                collect($this->baremeInitial)
                    ->mapWithKeys(fn ($points, $cle) => ['note_points_' . $cle => $points])
                    ->toArray()
            );
        }

        NotationAgent::oublierBareme();

        parent::tearDown();
    }

    private function noter(string $appreciation, int $combien = 1, ?string $commentaire = null): void
    {
        for ($i = 0; $i < $combien; $i++) {
            Note::create([
                'id_user' => self::CLIENT,
                'id_agent' => self::AGENT,
                'id_order' => random_int(500000, 999999),
                'note' => $appreciation,
                'comment' => $commentaire,
            ]);
        }
    }

    private function bareme(array $points): void
    {
        $active = Parameter::active();

        if (! $active) {
            $this->markTestSkipped('Aucune configuration active.');
        }

        $active->update([
            'note_points_verybad' => $points[0],
            'note_points_bad' => $points[1],
            'note_points_average' => $points[2],
            'note_points_good' => $points[3],
            'note_points_excellent' => $points[4],
        ]);

        NotationAgent::oublierBareme();
    }

    public function test_le_score_suit_le_bareme_configure(): void
    {
        $this->bareme([-2, -1, 1, 1.5, 2]);
        $this->noter('verybad');
        $this->noter('bad', 2);
        $this->noter('average', 3);
        $this->noter('good', 4);
        $this->noter('excellent', 5);

        $bilan = NotationAgent::pourAgent(self::AGENT);

        $this->assertSame(15, $bilan['nombre']);
        $this->assertEqualsWithDelta(-2 - 2 + 3 + 6 + 10, $bilan['total'], 0.01);
    }

    /*
     | Changer le barème change les scores, sans toucher aux notes.
     |
     | C'est tout l'intérêt de le rendre configurable : l'administrateur doit
     | pouvoir décider qu'une mauvaise prestation coûte cher sans redemander
     | leur avis aux clients.
     */
    public function test_changer_le_bareme_change_le_score_sans_retoucher_les_notes(): void
    {
        $this->noter('excellent', 3);
        $this->noter('bad', 1);

        $this->bareme([-2, -1, 1, 1.5, 2]);
        $avant = NotationAgent::pourAgent(self::AGENT);

        $this->bareme([-5, -3, 0, 3, 5]);
        $apres = NotationAgent::pourAgent(self::AGENT);

        $this->assertEqualsWithDelta(3 * 2 - 1, $avant['total'], 0.01);
        $this->assertEqualsWithDelta(3 * 5 - 3, $apres['total'], 0.01);
        $this->assertSame($avant['nombre'], $apres['nombre'], 'Les notes elles-mêmes ne bougent pas.');
    }

    /*
     | Un agent dont les notes s'annulent n'est pas un agent sans notes.
     |
     | L'ancien endpoint renvoyait 404 dès que le total tombait à zéro : un agent
     | ayant reçu autant de bonnes que de mauvaises appréciations était présenté
     | comme n'ayant jamais été noté.
     */
    public function test_un_total_nul_n_est_pas_une_absence_de_notes(): void
    {
        $this->bareme([-2, -1, 1, 1.5, 2]);
        $this->noter('bad', 2);
        $this->noter('average', 2);

        $bilan = NotationAgent::pourAgent(self::AGENT);
        $this->assertSame(4, $bilan['nombre']);
        $this->assertEqualsWithDelta(0, $bilan['total'], 0.01);

        $reponse = $this->getJson('/api/v1.0/getAgentNote?id_agent=' . self::AGENT);
        $reponse->assertOk()->assertJsonPath('response', 200);
        $this->assertSame(4, $reponse->json('data.nombre'));
    }

    public function test_un_agent_sans_note_recoit_des_compteurs_a_zero(): void
    {
        $bilan = NotationAgent::pourAgent(999999);

        $this->assertSame(0, $bilan['nombre']);
        $this->assertNull($bilan['moyenne'], 'Aucune moyenne ne peut être calculée sans note.');
        $this->assertNull($bilan['sur_cinq']);
    }

    /*
     | La note sur cinq classe les agents ; le total récompense l'ancienneté.
     |
     | Dix courses excellentes doivent primer sur mille courses moyennes quand on
     | cherche le mieux noté.
     */
    public function test_la_note_sur_cinq_ne_recompense_pas_le_seul_volume(): void
    {
        $this->bareme([-2, -1, 1, 1.5, 2]);
        $this->noter('excellent', 10);
        $excellent = NotationAgent::pourAgent(self::AGENT);

        Note::where('id_agent', self::AGENT)->delete();
        $this->noter('average', 200);
        $moyen = NotationAgent::pourAgent(self::AGENT);

        $this->assertGreaterThan($excellent['total'], $moyen['total'], 'Le total suit le volume.');
        $this->assertGreaterThan($moyen['sur_cinq'], $excellent['sur_cinq'], 'La note sur 5 suit la qualité.');
    }

    public function test_la_pire_appreciation_est_desormais_acceptee(): void
    {
        $reponse = $this->postJson('/api/v1.0/makeNote', [
            'id_user' => self::CLIENT,
            'id_agent' => self::AGENT,
            'id_order' => 987654,
            'note' => 'verybad',
            'comment' => 'Colis abîmé',
        ]);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $this->assertSame(1, Note::where('id_agent', self::AGENT)->where('note', 'verybad')->count());
    }

    public function test_une_prestation_ne_se_note_qu_une_fois(): void
    {
        $charge = [
            'id_user' => self::CLIENT,
            'id_agent' => self::AGENT,
            'id_order' => 987655,
            'note' => 'good',
        ];

        $this->postJson('/api/v1.0/makeNote', $charge)->assertJsonPath('response', 200);
        $this->postJson('/api/v1.0/makeNote', $charge)->assertJsonPath('response', 404);

        $this->assertSame(1, Note::where('id_order', 987655)->count());
    }

    public function test_noter_sans_prestation_est_refuse(): void
    {
        $this->postJson('/api/v1.0/makeNote', [
            'id_user' => self::CLIENT,
            'id_agent' => self::AGENT,
            'note' => 'good',
        ])->assertJsonPath('response', 422);
    }

    /** L'agent doit lire ce qu'on lui reproche, pas seulement sa moyenne. */
    public function test_le_detail_porte_les_commentaires_et_la_prestation(): void
    {
        $this->noter('bad', 1, 'Livraison très en retard');

        $donnees = $this->getJson('/api/v1.0/getNotesAgent?id_agent=' . self::AGENT)
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $donnees['bilan']['nombre']);
        $this->assertSame('Livraison très en retard', $donnees['appreciations'][0]['commentaire']);
        $this->assertSame('commande', $donnees['appreciations'][0]['prestation']);
        $this->assertNotEmpty($donnees['appreciations'][0]['emoji']);
    }

    /** Les deux applications doivent afficher le barème du tableau de bord. */
    public function test_le_bareme_est_exposé_aux_applications(): void
    {
        $this->bareme([-4, -2, 0, 2, 4]);

        $data = $this->getJson('/api/v1.0/getBaremeNotation')->assertOk()->json('data');

        $this->assertCount(5, $data);
        $this->assertSame('verybad', $data[0]['cle']);
        $this->assertEqualsWithDelta(-4, $data[0]['points'], 0.01);
        $this->assertEqualsWithDelta(4, $data[4]['points'], 0.01);
        $this->assertNotEmpty($data[0]['emoji']);
    }
}
