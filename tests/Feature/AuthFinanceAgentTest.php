<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthFinanceAgentTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    private function creerAgent(): User
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;

        return $agent;
    }

    public function test_getfinanceAgent_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getfinanceAgent')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getPaymentsAgent_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getPaymentsAgent')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getWithdrawalStatus_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getWithdrawalStatus')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_requestWithdrawal_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/requestWithdrawal', ['montant' => 1000, 'mode' => 'cash'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_requestWithdrawal_utilise_l_appelant_authentifie_pas_le_id_user_du_client(): void
    {
        if (! Schema::hasTable('credit_agents') || ! Schema::hasTable('withdrawal_requests')) {
            $this->markTestSkipped('Tables credit_agents/withdrawal_requests absentes de cette base locale.');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        \Illuminate\Support\Facades\DB::table('credit_agents')->insert([
            'id_agent' => $agent->id,
            'amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(\App\Support\LivreDeComptes::class)->reportOuverture('agent', $agent->id, 10000);

        $this->postJson('/api/v1.0/requestWithdrawal', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'montant' => 1000,
            'mode' => 'cash',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('withdrawal_requests', ['id_agent' => $agent->id, 'amount' => 1000]);
        $this->assertDatabaseMissing('withdrawal_requests', ['id_agent' => $victime->id]);

        \App\Models\WithdrawalRequest::where('id_agent', $agent->id)->delete();
        \Illuminate\Support\Facades\DB::table('credit_agents')->where('id_agent', $agent->id)->delete();
        if (Schema::hasTable('mouvements_financiers')) {
            \App\Models\MouvementFinancier::where('acteur_type', 'agent')->where('acteur_id', $agent->id)->delete();
        }
    }
}
