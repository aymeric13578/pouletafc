<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthSuiviServiceTest extends TestCase
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

    private function creerAgent(): User
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;

        return $agent;
    }

    public function test_takeDay_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/takeDay', ['ref' => 'peu-importe', 'lat' => 9.3, 'lon' => 13.4])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_takeDay_active_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        if (! Schema::hasTable('begin_agent_days')) {
            $this->markTestSkipped('Table begin_agent_days absente de cette base locale.');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/takeDay', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertSame(1, (int) $agent->in_activity);
        $this->assertNotSame(1, (int) $victime->in_activity);
    }

    public function test_takeDayDesactive_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/takeDayDesactive', ['ref' => 'peu-importe'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_takeDayDesactive_desactive_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        if (! Schema::hasTable('begin_agent_days')) {
            $this->markTestSkipped('Table begin_agent_days absente de cette base locale.');
        }

        $victime = $this->creerAgent();
        $victime->update(['in_activity' => 1]);
        $agent = $this->creerAgent();
        $agent->update(['in_activity' => 1]); // Start active
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/takeDayDesactive', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertSame(0, (int) $agent->in_activity);
        $this->assertSame(1, (int) $victime->in_activity, "Le compte visé par le ref du client ne doit jamais être modifié.");
    }

    public function test_updateDeliveryPosition_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateDeliveryPosition', ['id_user' => 999999, 'lat' => 9.3, 'lon' => 13.4])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updateDeliveryPosition_ignore_le_id_user_du_client(): void
    {
        if (! Schema::hasColumn('users', 'latitude') || ! Schema::hasColumn('users', 'longitude')) {
            $this->markTestSkipped('Colonnes latitude/longitude absentes de la table users.');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateDeliveryPosition', [
            'token' => $jeton,
            'id_user' => $victime->id,
            'lat' => 9.3,
            'lon' => 13.4,
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertEqualsWithDelta(9.3, (float) $agent->latitude, 0.0001);
        $this->assertNull($victime->latitude);
    }
}
