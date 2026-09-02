<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\MouvementFinancier;
use App\Models\User;
use App\Models\order_detail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTerminatedCourseOrderTest extends TestCase
{
    private array $utilisateursCrees = [];
    private array $agentsCrees = [];
    private ?order_detail $commande = null;

    protected function tearDown(): void
    {
        if ($this->commande && Schema::hasTable('mouvements_financiers')) {
            MouvementFinancier::where('source_type', 'order')->where('source_id', $this->commande->id)->delete();
        }
        $this->commande?->delete();
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

    public function test_terminatedCourseOrder_sans_jeton_c_est_401(): void
    {
        $this->postJson('/api/v1.0/terminatedCourseOrder', ['ref' => 'REF-INEXISTANTE', 'code' => '0000'])
            ->assertOk()->assertJsonPath('response', 401);
    }

    public function test_terminatedCourseOrder_avec_un_autre_agent_c_est_403_meme_avec_le_bon_code(): void
    {
        if (! Schema::hasColumn('agents', 'id_user')) {
            $this->markTestSkipped('Colonne agents.id_user absente de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $this->agentsCrees[] = Agent::create(['id_user' => $agentAssigne->id]);
        $autreAgent = $this->creerAgent();

        $this->commande = order_detail::create([
            'ref' => 'TEST-TERMO-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
            'delivery_code' => '4242',
        ]);

        $jeton = $autreAgent->createToken('agent-mobile')->plainTextToken;

        $this->postJson('/api/v1.0/terminatedCourseOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'code' => '4242',
            'payment_method' => 'LIVRAISON',
        ])->assertOk()->assertJsonPath('response', 403);

        $this->commande->refresh();
        $this->assertSame('process', $this->commande->status, "Même en connaissant le code de livraison, un agent non assigné ne doit jamais pouvoir terminer la commande.");
    }

    public function test_terminatedCourseOrder_ignore_le_id_user_du_payload_et_credite_l_agent_assigne(): void
    {
        if (! Schema::hasColumn('agents', 'id_user') || ! Schema::hasColumn('agents', 'deposit_recu') || ! Schema::hasColumn('agents', 'freeStatus')) {
            $this->markTestSkipped('Colonnes agents.id_user/deposit_recu/freeStatus absentes de cette base locale.');
        }

        $agentAssigne = $this->creerAgent();
        $ficheAssignee = Agent::create(['id_user' => $agentAssigne->id, 'deposit_recu' => 0, 'freeStatus' => 0]);
        $this->agentsCrees[] = $ficheAssignee;

        $intrus = $this->creerAgent();
        $ficheIntrus = Agent::create(['id_user' => $intrus->id, 'deposit_recu' => 0, 'freeStatus' => 0]);
        $this->agentsCrees[] = $ficheIntrus;

        $this->commande = order_detail::create([
            'ref' => 'TEST-TERMO-' . uniqid(),
            'id_agent' => $agentAssigne->id,
            'status' => 'process',
            'price' => 1000,
            'commission_agent' => 200,
            'delivery_code' => '4242',
            'delivery_type' => 'coursier',
        ]);

        $jeton = $agentAssigne->createToken('agent-mobile')->plainTextToken;

        // L'agent réellement assigné (donc autorisé à appeler cette route)
        // envoie l'id d'un autre agent dans id_user, en tentant de
        // rediriger le crédit/la dette vers ce compte tiers.
        $this->postJson('/api/v1.0/terminatedCourseOrder', [
            'token' => $jeton,
            'ref' => $this->commande->ref,
            'code' => '4242',
            'payment_method' => 'LIVRAISON',
            'id_user' => $intrus->id,
        ])->assertOk()->assertJsonPath('response', 200);

        $ficheAssignee->refresh();
        $ficheIntrus->refresh();
        $this->assertSame(1000, (int) $ficheAssignee->deposit_recu, "Le crédit doit toujours suivre l'agent réellement assigné à la commande, jamais le id_user du payload.");
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
                "Le livre de comptes doit créditer l'agent réellement assigné à la commande."
            );
            // Nettoyage réel dans tearDown() (par source_type/source_id, pas
            // par acteur_id) : couvre aussi la ligne acteur=société écrite
            // par courseOm/livraisonOm, et s'exécute même si une assertion
            // ci-dessus échoue.
        }
    }
}
