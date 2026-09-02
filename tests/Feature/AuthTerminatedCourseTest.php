<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\MouvementFinancier;
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
        if ($this->clando && Schema::hasTable('mouvements_financiers')) {
            MouvementFinancier::where('source_type', 'clando')->where('source_id', $this->clando->id)->delete();
        }
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

    public function test_terminatedCourse_ignore_le_id_user_du_payload_et_credite_l_agent_assigne(): void
    {
        if (! Schema::hasTable('clando')) {
            $this->markTestSkipped('Table clando absente de cette base locale.');
        }
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'deposit_recu') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/deposit_recu/freeStatus absentes de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $ficheAssignee = Agent::create(['id_user' => $agentAssigne->id, 'deposit_recu' => 0, 'freeStatus' => 0]);
        $this->agentsCrees[] = $ficheAssignee;

        $intrus = $this->creerAgent();
        $ficheIntrus = Agent::create(['id_user' => $intrus->id, 'deposit_recu' => 0, 'freeStatus' => 0]);
        $this->agentsCrees[] = $ficheIntrus;

        $this->clando = Clando::create([
            'ref' => 'TEST-TERM-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        // L'agent réellement assigné (donc autorisé à appeler cette route)
        // envoie l'id d'un autre agent dans id_user, en tentant de
        // rediriger le crédit/la dette vers ce compte tiers.
        $this->postJson('/api/v1.0/terminatedCourse', [
            'token' => $jeton,
            'ref' => $this->clando->ref,
            'payment_method' => 'cash',
            'id_user' => $intrus->id,
        ])->assertOk()->assertJsonPath('response', 200);

        $ficheAssignee->refresh();
        $ficheIntrus->refresh();
        $this->assertSame(1000, (int) $ficheAssignee->deposit_recu, "Le crédit doit toujours suivre l'agent réellement assigné à la course, jamais le id_user du payload.");
        $this->assertSame(1, (int) $ficheAssignee->freeStatus);
        $this->assertSame(0, (int) $ficheIntrus->deposit_recu, "Un id_user tiers dans le payload ne doit jamais rediriger le crédit vers ce compte.");
        $this->assertSame(0, (int) $ficheIntrus->freeStatus);

        if (Schema::hasTable('mouvements_financiers')) {
            $this->assertSame(
                0,
                MouvementFinancier::where('acteur_type', MouvementFinancier::ACTEUR_AGENT)
                    ->where('acteur_id', $intrus->id)
                    ->count(),
                "Aucune ligne du livre de comptes ne doit être écrite pour l'agent tiers désigné par id_user."
            );
            $this->assertGreaterThan(
                0,
                MouvementFinancier::where('acteur_type', MouvementFinancier::ACTEUR_AGENT)
                    ->where('acteur_id', $agentAssigne->id)
                    ->count(),
                "Le livre de comptes doit créditer l'agent réellement assigné à la course."
            );
            // Nettoyage réel dans tearDown() (par source_type/source_id, pas
            // par acteur_id) : couvre aussi la ligne acteur=société écrite
            // par courseOm/livraisonOm, et s'exécute même si une assertion
            // ci-dessus échoue.
        }
    }
}
