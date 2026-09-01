<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthMapAftertakeTest extends TestCase
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

    public function test_mapAftertakeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/mapAftertakeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_mapAftertakeOrder_avec_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status);
    }

    public function test_mapAftertakeOrder_avec_le_bon_agent_passe_a_take(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('take', $this->commande->status);
    }

    public function test_mapAftertakeOrder_admin_contourne_la_propriete(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $admin = $this->creerUtilisateur('admin');

        $this->commande = order_detail::create([
            'ref' => 'TEST-MAT-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $admin->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/mapAftertakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 200);
    }
}
