<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthProfilTest extends TestCase
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

    public function test_getInfoUser_sans_jeton_c_est_401(): void
    {
        $this->getJson('/api/v1.0/getInfoUser?ref=peu-importe')
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_getInfoUser_ignore_le_ref_du_client_et_renvoie_le_bon_compte(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale — getInfoUser ne peut pas charger la relation agent ici (préexistant, sans lien avec ce plan).');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $reponse = $this->getJson('/api/v1.0/getInfoUser?token=' . $jeton . '&ref=' . $victime->ref);

        $reponse->assertOk()->assertJsonPath('response', 200);
        $data = $reponse->json('data');
        $this->assertCount(1, $data, 'getInfoUser ne doit renvoyer que le compte authentifié, jamais celui visé par le ref du client.');
        $this->assertSame($agent->ref, $data[0]['ref']);
    }

    public function test_updateUser_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/updateUser', ['ref' => 'peu-importe', 'name' => 'X'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_updateUser_modifie_l_appelant_authentifie_pas_le_ref_du_client(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'whatsapp')) {
            $this->markTestSkipped('Colonne users.whatsapp absente de cette base locale — updateUser ne peut pas s\'exécuter ici (préexistant, sans lien avec ce plan).');
        }

        $victime = $this->creerAgent();
        $agent = $this->creerAgent();
        $jeton = $agent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/updateUser', [
            'token' => $jeton,
            'ref' => $victime->ref,
            'name' => 'Nom modifié',
        ])->assertOk()->assertJsonPath('response', 200);

        $agent->refresh();
        $victime->refresh();
        $this->assertSame('Nom modifié', $agent->name, "Le nom de l'appelant authentifié doit changer.");
        $this->assertNotSame('Nom modifié', $victime->name, "Le compte visé par le ref du client ne doit jamais être modifié.");
    }
}
