<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthDeclinOrderCommandTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function tearDown(): void
    {
        foreach ($this->utilisateursCrees as $utilisateur) {
            $utilisateur->tokens()->delete();
            $utilisateur->delete();
        }

        parent::tearDown();
    }

    public function test_declinOrderCommand_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/declinOrderCommand?id_order=999999')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_declinOrderCommand_enregistre_l_appelant_authentifie_pas_le_id_user_du_client(): void
    {
        if (! Schema::hasTable('declin_command')) {
            $this->markTestSkipped('Table declin_command absente de cette base locale.');
        }

        $victime = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $victime;
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->getJson('/api/v1.0/declinOrderCommand?token=' . $jeton . '&id_order=42&id_user=' . $victime->id)
            ->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseHas('declin_command', ['id_user' => $agent->id, 'id_order' => 42]);
        $this->assertDatabaseMissing('declin_command', ['id_user' => $victime->id, 'id_order' => 42]);

        \Illuminate\Support\Facades\DB::table('declin_command')->where('id_user', $agent->id)->where('id_order', 42)->delete();
    }
}
