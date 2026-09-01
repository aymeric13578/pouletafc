<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPriseCommandeTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->commande?->delete();
        foreach ($this->agentsCrees as $agent) {
            $agent->delete();
        }
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

    public function test_takeOrderCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/takeOrderCommand?ref=REF-INEXISTANTE&id_agent=1')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_takeOrderCommand_assigne_l_appelant_authentifie_pas_le_id_agent_du_client(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/freeStatus absentes de cette base locale.');
        }

        $agentAppelant = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAppelant->id]);
        $victime = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-TAKE-' . uniqid(),
            'id_agent' => null,
            'status' => 'want',
            'price' => 500,
            'commission_agent' => 0,
        ]);

        $jeton = $agentAppelant->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/takeOrderCommand?token=' . $jeton . '&ref=' . $this->commande->ref . '&id_agent=' . $victime->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame($agentAppelant->id, $this->commande->id_agent, "La commande doit être assignée à l'appelant authentifié, jamais au id_agent fourni par le client.");
    }
}
