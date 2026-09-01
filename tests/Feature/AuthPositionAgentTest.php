<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthPositionAgentTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['actual_lat_position_agent', 'actual_lon_position_agent'] as $colonne) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('users', $colonne)) {
                $this->markTestSkipped("Colonne users.$colonne absente de cette base locale.");
            }
        }
    }

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

    public function test_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateAgentPosition', [
            'id_user' => 999999,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 401);
    }

    public function test_avec_jeton_met_a_jour_la_position_de_l_appelant(): void
    {
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateAgentPosition', [
            'token' => $jeton,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $agent->actual_lat_position_agent, 0.0001);
        $this->assertEqualsWithDelta(13.4, (float) $agent->actual_lon_position_agent, 0.0001);
    }

    public function test_le_id_user_envoye_par_le_client_est_ignore(): void
    {
        $agent = $this->creerAgent();
        $victime = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateAgentPosition', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $victime->refresh();
        $this->assertNull($victime->actual_lat_position_agent, "La position de la victime ne doit jamais être modifiée par le jeton d'un autre compte.");
    }
}
