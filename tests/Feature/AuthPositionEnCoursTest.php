<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthPositionEnCoursTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?Clando $clando = null;
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        $this->clando?->delete();
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

    private function creerUtilisateur(string $role): User
    {
        $utilisateur = User::factory()->create(['role' => $role, 'status' => 'Success']);
        $this->utilisateursCrees[] = $utilisateur;

        return $utilisateur;
    }

    private function creerFicheAgent(User $utilisateur): Agent
    {
        $agent = Agent::create(['id_user' => $utilisateur->id]);
        $this->agentsCrees[] = $agent;

        return $agent;
    }

    public function test_updatePositionAgentOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'ref' => 'REF-INEXISTANTE',
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updatePositionAgentOrder_avec_le_jeton_d_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertNull($this->commande->latAgent, "La position ne doit pas bouger quand l'appelant n'est pas l'agent assigné.");
    }

    public function test_updatePositionAgentOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $this->commande->latAgent, 0.0001);
    }

    public function test_updatePositionAgentOrder_employee_afc_contourne_la_propriete(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $employe = $this->creerUtilisateur('employee_afc');

        $this->commande = order_detail::create([
            'ref' => 'TEST-POS-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $employe->createToken('employee-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updatePositionAgentOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'latAgent' => 9.3,
            'lonAgent' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);
    }
}
