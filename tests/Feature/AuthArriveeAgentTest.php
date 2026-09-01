<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthArriveeAgentTest extends TestCase
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

    public function test_arriveeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/arriveeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_arriveeOrder_avec_un_autre_agent_ne_trouve_pas_la_commande(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-ARR-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        // La requête ne trouve pas la commande (filtrée par id_agent =
        // l'appelant) : réponse identique à "commande introuvable", pas un
        // 403 distinct — comportement déjà en place avant ce plan, conservé.
        $this->postJson('/api/v1.0/arriveeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 400);

        $this->commande->refresh();
        $this->assertNull($this->commande->agent_arrived_at);
    }

    public function test_arriveeOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('order_details', 'agent_arrived_at')) {
            $this->markTestSkipped('Colonne agents.id_user ou order_details.agent_arrived_at absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);

        $this->commande = order_detail::create([
            'ref' => 'TEST-ARR-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/arriveeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertNotNull($this->commande->agent_arrived_at);
    }
}
