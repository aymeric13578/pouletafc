<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinCommandTest extends TestCase
{
    private array $utilisateursCrees = [];
    private ?Clando $clando = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('clando')) {
            $this->markTestSkipped('Table clando absente de cette base locale.');
        }
    }

    protected function tearDown(): void
    {
        $this->clando?->delete();
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

    public function test_declinCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/declinCommand?id_clando=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinCommand_le_client_proprietaire_peut_annuler(): void
    {
        $client = $this->creerUtilisateur('user');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'status' => 'want',
            'price' => 1000,
        ]);

        $jeton = $client->createToken('client-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 200);
    }

    public function test_declinCommand_un_agent_peut_decliner_une_course_non_assignee(): void
    {
        $client = $this->creerUtilisateur('user');
        $agent = $this->creerUtilisateur('agent');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'id_agent' => null,
            'status' => 'want',
            'price' => 1000,
        ]);

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 200);
    }

    public function test_declinCommand_un_tiers_sans_lien_c_est_403(): void
    {
        $client = $this->creerUtilisateur('user');
        $agentAssigne = $this->creerUtilisateur('agent');
        $intrus = $this->creerUtilisateur('agent');
        $this->clando = Clando::create([
            'ref' => 'TEST-DECLIN-' . uniqid(),
            'id_user' => $client->id,
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
        ]);

        $jeton = $intrus->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinCommand?token=' . $jeton . '&id_clando=' . $this->clando->id)
            ->assertOk()->assertJsonPath('response', 403);
    }
}
