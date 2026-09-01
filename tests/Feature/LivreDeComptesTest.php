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

    public function test_deux_boutiques_sur_la_meme_commande_apportent_chacune_leur_majoration(): void
    {
        $soldeSocieteAvant = $this->livre->solde('societe', null);

        // Une commande mêlant deux boutiques : sans la boutique dans la clé
        // d'idempotence, la seconde majoration serait silencieusement perdue.
        $this->livre->venteOm(self::ID_BOUTIQUE, 1000, 100, 'order', 424242, 'REF_T8');
        $this->livre->venteOm(self::ID_BOUTIQUE + 1, 500, 50, 'order', 424242, 'REF_T8');

        $this->assertSame(
            round($soldeSocieteAvant + 150, 2),
            $this->livre->solde('societe', null),
        );

        MouvementFinancier::where('acteur_id', self::ID_BOUTIQUE + 1)->delete();
    }

    public function test_abonnement_debite_la_boutique_une_fois_par_echeance(): void
    {
        $this->livre->abonnement(self::ID_BOUTIQUE, 5000, '2026-09-30');
        $this->livre->abonnement(self::ID_BOUTIQUE, 5000, '2026-09-30'); // rejeu
        $this->livre->abonnement(self::ID_BOUTIQUE, 5000, '2026-10-31'); // mois suivant

        $this->assertSame(-10000.0, $this->livre->solde('boutique', self::ID_BOUTIQUE));
    }

    /**
     * finances:ouvrir tourne à chaque déploiement : un agent né APRÈS la
     * bascule a un livre déjà vivant mais aucun report — sans cette garde,
     * l'ouverture lui ajouterait l'ancienne formule par-dessus ses lignes,
     * comptant tout deux fois.
     */
    public function test_l_ouverture_saute_un_agent_dont_le_livre_est_deja_entame(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('agents')
            || ! \Illuminate\Support\Facades\Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Table `agents` incomplète dans cette base.');
        }

        \Illuminate\Support\Facades\DB::table('agents')->where('id_user', self::ID_AGENT)->delete();
        \Illuminate\Support\Facades\DB::table('agents')->insert([
            'id_user' => self::ID_AGENT,
            'agent_name' => 'Agent né après bascule',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sa vie a commencé au livre : une course cash, rien d'autre.
        $this->livre->courseCash(self::ID_AGENT, 200, 'clando', 424242, 'REF_T9');

        $this->artisan('finances:ouvrir')->assertSuccessful();

        // Aucun report ajouté : son solde reste exactement sa seule ligne.
        $this->assertSame(-200.0, $this->livre->solde('agent', self::ID_AGENT));
        $this->assertSame(
            0,
            MouvementFinancier::where('acteur_id', self::ID_AGENT)
                ->where('type', MouvementFinancier::REPORT_OUVERTURE)->count(),
        );

        \Illuminate\Support\Facades\DB::table('agents')->where('id_user', self::ID_AGENT)->delete();
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
