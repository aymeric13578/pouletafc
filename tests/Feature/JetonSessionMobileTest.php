<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiAuthentification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JetonSessionMobileTest extends TestCase
{
    private array $utilisateursCrees = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure role enum includes 'employee_afc' and 'client' for tests
        try {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'agent', 'admin', 'employee_afc', 'client') DEFAULT 'user'");
        } catch (\Exception $e) {
            // Enum might already be extended or database might not support it
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

    public function test_loginDelivery_renvoie_un_jeton_utilisable(): void
    {
        $agent = $this->creerAgent();

        $reponse = $this->postJson('/api/v1.0/loginDelivery', [
            'email' => $agent->email,
            'password' => 'password',
        ]);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $jeton = $reponse->json('token');

        $this->assertNotEmpty($jeton, 'loginDelivery doit renvoyer un jeton.');

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($agent));
    }

    private function creerEmploye(): User
    {
        $employe = User::factory()->create(['role' => 'employee_afc', 'status' => 'Success']);
        $this->utilisateursCrees[] = $employe;

        return $employe;
    }

    public function test_loginEmployee_renvoie_un_jeton_utilisable(): void
    {
        $employe = $this->creerEmploye();

        $reponse = $this->postJson('/api/v1.0/loginEmployee', [
            'email' => $employe->email,
            'password' => 'password',
        ]);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $jeton = $reponse->json('token');

        $this->assertNotEmpty($jeton, 'loginEmployee doit renvoyer un jeton.');

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($employe));
    }
}
