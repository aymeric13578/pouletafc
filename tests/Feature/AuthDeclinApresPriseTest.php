<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinApresPriseTest extends TestCase
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

    public function test_declinCommandAfterTakeOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinCommandAfterTakeOrder_avec_un_autre_agent_c_est_403(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);
        $autreAgent = $this->creerUtilisateur('agent');

        $this->commande = order_detail::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref])
            ->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status, "Le statut ne doit pas changer si l'appelant n'est pas l'agent assigné.");
    }

    public function test_declinCommandAfterTakeOrder_avec_le_bon_agent_fonctionne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/freeStatus absentes de cette base locale.');
        }

        $agentAssigne = $this->creerUtilisateur('agent');
        $this->creerFicheAgent($agentAssigne);

        $this->commande = order_detail::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 500,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/declinCommandAfterTakeOrder', ['token' => $jeton, 'ref' => $this->commande->ref, 'reason' => 'Client injoignable'])
            ->assertOk()->assertJsonPath('response', 200);

        $this->commande->refresh();
        $this->assertSame('declin', $this->commande->status);
    }
}
