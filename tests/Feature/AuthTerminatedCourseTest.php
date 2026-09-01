<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTerminatedCourseTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?Clando $clando = null;

    protected function tearDown(): void
    {
        $this->clando?->delete();
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

    public function test_terminatedCourse_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/terminatedCourse', ['ref' => 'REF-INEXISTANTE'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_terminatedCourse_avec_un_autre_agent_c_est_403_avant_tout_credit(): void
    {
        if (! Schema::hasTable('clando')) {
            $this->markTestSkipped('Table clando absente de cette base locale.');
        }
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->clando = Clando::create([
            'ref' => 'TEST-TERM-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/terminatedCourse', [
            'token' => $jeton,
            'ref' => $this->clando->ref,
            'payment_method' => 'cash',
        ])->assertOk()->assertJsonPath('response', 403);

        $this->clando->refresh();
        $this->assertSame('process', $this->clando->status, "Un agent non assigné ne doit jamais pouvoir terminer — ni changer le statut, ni déclencher le crédit financier qui suivrait.");
    }
}
