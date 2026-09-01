<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiAuthentification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiAuthentificationTest extends TestCase
{
    private ?User $utilisateur = null;

    protected function tearDown(): void
    {
        if ($this->utilisateur) {
            $this->utilisateur->tokens()->delete();
            $this->utilisateur->delete();
        }

        parent::tearDown();
    }

    public function test_un_jeton_valide_resout_vers_son_proprietaire(): void
    {
        $this->utilisateur = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $jeton = $this->utilisateur->createToken('test')->plainTextToken;

        $requete = Request::create('/', 'POST', ['token' => $jeton]);

        $resolu = app(ApiAuthentification::class)->utilisateur($requete);

        $this->assertNotNull($resolu);
        $this->assertTrue($resolu->is($this->utilisateur));
    }

    public function test_un_jeton_invalide_ne_resout_personne(): void
    {
        $requete = Request::create('/', 'POST', ['token' => 'ceci-n-est-pas-un-jeton']);

        $this->assertNull(app(ApiAuthentification::class)->utilisateur($requete));
    }

    public function test_l_absence_de_jeton_ne_resout_personne(): void
    {
        $requete = Request::create('/', 'POST', []);

        $this->assertNull(app(ApiAuthentification::class)->utilisateur($requete));
    }

    public function test_utilisateurOuErreur_renvoie_l_utilisateur_si_le_jeton_est_valide(): void
    {
        $this->utilisateur = User::factory()->create(['role' => 'agent', 'status' => 'Success']);
        $jeton = $this->utilisateur->createToken('test')->plainTextToken;

        $requete = Request::create('/', 'POST', ['token' => $jeton]);

        $resultat = app(ApiAuthentification::class)->utilisateurOuErreur($requete);

        $this->assertInstanceOf(User::class, $resultat);
        $this->assertTrue($resultat->is($this->utilisateur));
    }

    public function test_utilisateurOuErreur_renvoie_une_reponse_401_si_le_jeton_est_absent(): void
    {
        $requete = Request::create('/', 'POST', []);

        $resultat = app(ApiAuthentification::class)->utilisateurOuErreur($requete);

        $this->assertInstanceOf(JsonResponse::class, $resultat);
        $this->assertSame(401, $resultat->getData()->response);
    }
}
