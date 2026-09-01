<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTerminatedCourseOrderTest extends TestCase
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

    public function test_terminatedCourseOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/terminatedCourseOrder', ['ref' => 'REF-INEXISTANTE', 'code' => '0000'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_terminatedCourseOrder_avec_un_autre_agent_c_est_403_meme_avec_le_bon_code(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-TERMO-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
            'delivery_code' => '4242',
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/terminatedCourseOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'code' => '4242',
            'payment_method' => 'LIVRAISON',
        ])->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status, "Même en connaissant le code de livraison, un agent non assigné ne doit jamais pouvoir terminer la commande.");
    }
}
