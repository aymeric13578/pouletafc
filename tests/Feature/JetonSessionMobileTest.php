<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiAuthentification;
use Tests\TestCase;

class JetonSessionMobileTest extends TestCase
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
        try {
            $employe = User::factory()->create(['role' => 'employee_afc', 'status' => 'Success']);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->markTestSkipped("La colonne users.role de cette base ne supporte pas encore 'employee_afc' : {$e->getMessage()}");
        }

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

    public function test_changePassword_revoque_les_jetons_existants(): void
    {
        $agent = $this->creerAgent();
        $agent->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('ancien-mdp')])->save();

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/changePassword', [
            'ref' => $agent->ref,
            'password' => 'ancien-mdp',
            'newpassword' => 'nouveau-mdp',
            'confirmpassword' => 'nouveau-mdp',
        ])->assertOk()->assertJsonPath('response', 200);

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNull($resolu, 'Le jeton émis avant le changement de mot de passe doit être révoqué.');
    }

    public function test_changePasswordByOtp_revoque_les_jetons_existants(): void
    {
        $agent = $this->creerAgent();
        $agent->forceFill(['confirmation_code' => '54321'])->save();

        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/changePasswordByOtp', [
            'method' => 'email',
            'value' => $agent->email,
            'otp' => '54321',
            'password' => 'nouveau-mdp',
        ])->assertOk()->assertJsonPath('response', 200);

        $requete = \Illuminate\Http\Request::create('/', 'POST', ['token' => $jeton]);
        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNull($resolu, 'Le jeton émis avant la réinitialisation OTP doit être révoqué.');
    }

    public function test_deleteUser_supprime_les_jetons_du_compte(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        // Enregistré comme les autres tests : purgeAccount() supprime déjà
        // la ligne dans le cas nominal, mais un second delete() en
        // tearDown() sur une ligne absente est un no-op sûr côté Eloquent
        // (aucune exception) — ce filet couvre le cas où une assertion
        // échouerait avant que deleteUser n'ait réellement supprimé le compte.
        $this->utilisateursCrees[] = $agent;

        $agent->createToken('agent-mobile');
        $this->assertSame(1, $agent->tokens()->count());

        $this->postJson('/api/v1.0/deleteUser', [
            'ref' => $agent->ref,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $agent->id,
        ]);
    }
}
