<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthCreationBoutiqueProduitTest extends TestCase
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

    public function test_storeProduct_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/storeProduct', ['designation_tech' => 'Test'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_storeProduct_avec_un_compte_agent_c_est_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/storeProduct', ['token' => $jeton, 'designation_tech' => 'Test'])
            ->assertOk()->assertJsonPath('response', 403);
    }

    public function test_addShop_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/addShop', ['shop_name' => 'Test'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_addShop_avec_un_compte_agent_c_est_403(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $this->utilisateursCrees[] = $agent;
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/addShop', ['token' => $jeton, 'shop_name' => 'Test'])
            ->assertOk()->assertJsonPath('response', 403);
    }
}
