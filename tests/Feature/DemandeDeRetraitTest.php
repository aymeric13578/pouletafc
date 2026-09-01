<?php

namespace Tests\Feature;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Demande de retrait depuis l'app agent — voir
 * FinanceController::requestWithdrawal.
 *
 * Le solde d'un agent (App\Fonction\Fonction::solde) vaut
 * dépôts + crédits − gains − retraits déjà validés : un crédit suffit donc à
 * donner un solde connu à un agent de test, sans avoir à fabriquer de
 * courses ni de commandes.
 */
class DemandeDeRetraitTest extends TestCase
{
    private const ID_AGENT = 999901;

    /**
     * `Fonction::solde()` interroge cinq tables dont trois sont absentes de
     * certaines bases de développement locales (`credit_agents`, `deposits`,
     * `clando` — dérive de schéma antérieure à cette fonctionnalité, ces
     * tables ne sont pas créées par une migration). Sans elles, aucun appel à
     * requestWithdrawal ne peut aboutir, quel que soit le code testé : on
     * marque alors le test ignoré plutôt que de le faire échouer pour une
     * raison sans rapport avec ce qu'il vérifie.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['credit_agents', 'deposits', 'clando', 'order_details', 'withdrawal_requests'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Table `$table` absente de cette base : schéma incomplet.");
            }
        }

        $this->nettoyer();
        DB::table('credit_agents')->insert([
            'id_agent' => self::ID_AGENT,
            'amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Depuis la bascule du 2026-09-01, le solde qui borne un retrait est
        // celui du livre de comptes : l'agent de test y reçoit le même
        // report d'ouverture qu'un vrai agent au moment de la bascule.
        app(\App\Support\LivreDeComptes::class)->reportOuverture('agent', self::ID_AGENT, 10000);
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    private function nettoyer(): void
    {
        if (Schema::hasTable('withdrawal_requests')) {
            WithdrawalRequest::where('id_agent', self::ID_AGENT)->delete();
        }
        if (Schema::hasTable('credit_agents')) {
            DB::table('credit_agents')->where('id_agent', self::ID_AGENT)->delete();
        }
        if (Schema::hasTable('mouvements_financiers')) {
            \App\Models\MouvementFinancier::where('acteur_type', 'agent')
                ->where('acteur_id', self::ID_AGENT)->delete();
        }
    }

    public function test_retrait_en_cash_enregistre_le_montant_demande(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 4000,
            'mode' => 'cash',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
            'amount' => 4000,
            'mode' => 'cash',
            'status' => 'pending',
        ]);
    }

    public function test_retrait_orange_money_enregistre_le_numero(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 2500,
            'mode' => 'om',
            'numero' => '690517917',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
            'amount' => 2500,
            'mode' => 'om',
            'phone' => '690517917',
        ]);
    }

    public function test_orange_money_sans_numero_est_refuse(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 2500,
            'mode' => 'om',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
        ]);
    }

    /**
     * Le solde fait foi côté serveur : l'app envoie un montant, elle ne
     * décide pas de ce qui est disponible. Sans ce contrôle, un client
     * modifié pourrait demander n'importe quelle somme.
     */
    public function test_montant_superieur_au_solde_est_refuse(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 15000, // solde = 10000
            'mode' => 'cash',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
        ]);
    }

    public function test_montant_nul_ou_negatif_est_refuse(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 0,
            'mode' => 'cash',
        ])->assertStatus(422);

        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => -500,
            'mode' => 'cash',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
        ]);
    }

    /**
     * Règle déjà en place avant l'ajout du montant/mode, conservée : une
     * seule demande en attente à la fois, un rappel renvoie l'existante.
     */
    public function test_une_seule_demande_en_attente_a_la_fois(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 1000,
            'mode' => 'cash',
        ])->assertOk()->assertJsonPath('already_pending', false);

        $this->postJson('/api/v1.0/requestWithdrawal', [
            'id_user' => self::ID_AGENT,
            'montant' => 3000,
            'mode' => 'cash',
        ])->assertOk()->assertJsonPath('already_pending', true);

        $this->assertSame(
            1,
            WithdrawalRequest::where('id_agent', self::ID_AGENT)->count(),
        );
        // La demande initiale n'est pas écrasée par le second appel.
        $this->assertDatabaseHas('withdrawal_requests', [
            'id_agent' => self::ID_AGENT,
            'amount' => 1000,
        ]);
    }
}
