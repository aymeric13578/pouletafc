<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPaiementModeTest extends TestCase
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

    public function test_setPaymentMethodOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['ref' => 'REF-INEXISTANTE', 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_setPaymentMethodOrder_avec_un_autre_agent_ne_trouve_pas_la_commande(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-PAY-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 400);

        $this->commande->refresh();
        $this->assertNull($this->commande->payment_method);
    }

    public function test_setPaymentMethodOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);

        $this->commande = order_detail::create([
            'ref' => 'TEST-PAY-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/setPaymentMethodOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'payment_method' => 'OM'])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('OM', $this->commande->payment_method);
    }
}
