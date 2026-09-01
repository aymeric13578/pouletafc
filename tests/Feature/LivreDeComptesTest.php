<?php

namespace Tests\Feature;

use App\Models\MouvementFinancier;
use App\Support\LivreDeComptes;
use Tests\TestCase;

/**
 * Le livre de comptes central — voir App\Support\LivreDeComptes.
 *
 * Chaque test vérifie une règle d'argent validée le 2026-09-01 : le sens et
 * le montant exacts de chaque type de mouvement, l'idempotence des rejeux,
 * et le solde comme somme de lignes.
 */
class LivreDeComptesTest extends TestCase
{
    private const ID_AGENT = 999902;
    private const ID_BOUTIQUE = 999903;

    private LivreDeComptes $livre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->livre = new LivreDeComptes();
        $this->nettoyer();
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    private function nettoyer(): void
    {
        MouvementFinancier::whereIn('acteur_id', [self::ID_AGENT, self::ID_BOUTIQUE])->delete();
        MouvementFinancier::where('source_id', 424242)->delete();
    }

    public function test_course_cash_debite_la_seule_commission(): void
    {
        // Course à 1000 F, commission 150 F : l'agent garde le cash en main,
        // son compte ne doit bouger que de la commission due.
        $this->livre->courseCash(self::ID_AGENT, 150, 'clando', 424242, 'REF_T1');

        $this->assertSame(-150.0, $this->livre->solde('agent', self::ID_AGENT));
        $this->assertDatabaseHas('mouvements_financiers', [
            'acteur_type' => 'agent',
            'acteur_id' => self::ID_AGENT,
            'sens' => 'debit',
            'type' => MouvementFinancier::COMMISSION_COURSE,
            'montant' => 150,
        ]);
    }

    public function test_course_om_credite_prix_moins_commission_et_la_societe(): void
    {
        $soldeSocieteAvant = $this->livre->solde('societe', null);

        $this->livre->courseOm(self::ID_AGENT, 1000, 150, 'clando', 424242, 'REF_T2');

        $this->assertSame(850.0, $this->livre->solde('agent', self::ID_AGENT));
        $this->assertSame(
            round($soldeSocieteAvant + 150, 2),
            $this->livre->solde('societe', null),
        );
    }

    public function test_rejouer_le_meme_evenement_ne_cree_pas_deux_lignes(): void
    {
        $this->livre->courseCash(self::ID_AGENT, 150, 'clando', 424242, 'REF_T3');
        $this->livre->courseCash(self::ID_AGENT, 150, 'clando', 424242, 'REF_T3');

        $this->assertSame(-150.0, $this->livre->solde('agent', self::ID_AGENT));
        $this->assertSame(1, MouvementFinancier::where('acteur_id', self::ID_AGENT)->count());
    }

    public function test_le_solde_est_la_somme_de_toutes_les_lignes(): void
    {
        $this->livre->reportOuverture('agent', self::ID_AGENT, 5000);
        $this->livre->courseCash(self::ID_AGENT, 150, 'clando', 424242, 'REF_T4');
        $this->livre->prime(self::ID_AGENT, 2000, 424242, 'Objectif de test');
        $this->livre->depot(self::ID_AGENT, 1000, 424242);
        $this->livre->retrait(self::ID_AGENT, 3000, 424242);

        // 5000 − 150 + 2000 + 1000 − 3000
        $this->assertSame(4850.0, $this->livre->solde('agent', self::ID_AGENT));
    }

    public function test_le_report_d_ouverture_est_idempotent_et_gere_un_solde_negatif(): void
    {
        $this->livre->reportOuverture('agent', self::ID_AGENT, -700);
        $this->livre->reportOuverture('agent', self::ID_AGENT, -700);

        $this->assertSame(-700.0, $this->livre->solde('agent', self::ID_AGENT));
        $this->assertSame(1, MouvementFinancier::where('acteur_id', self::ID_AGENT)->count());
    }

    public function test_vente_om_credite_la_boutique_net_et_la_societe_de_la_majoration(): void
    {
        $soldeSocieteAvant = $this->livre->solde('societe', null);

        // Vente affichée 1100 F dont 100 F de majoration : la boutique reçoit
        // son prix de base, la société sa majoration.
        $this->livre->venteOm(self::ID_BOUTIQUE, 1000, 100, 'order', 424242, 'REF_T5');

        $this->assertSame(1000.0, $this->livre->solde('boutique', self::ID_BOUTIQUE));
        $this->assertSame(
            round($soldeSocieteAvant + 100, 2),
            $this->livre->solde('societe', null),
        );
    }

    public function test_un_mouvement_nul_n_ecrit_rien(): void
    {
        // Boutique sans majoration : pas de ligne société à zéro.
        $this->livre->venteOm(self::ID_BOUTIQUE, 1000, 0, 'order', 424242, 'REF_T6');

        $this->assertSame(
            0,
            MouvementFinancier::where('type', MouvementFinancier::MAJORATION)
                ->where('source_id', 424242)->count(),
        );
    }
}
