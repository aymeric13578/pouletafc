<?php

namespace Tests\Feature;

use App\Fonction\Fonction;
use App\Models\Agent;
use App\Models\Clando;
use App\Models\order_detail;
use Tests\TestCase;

/**
 * Attribution d'une course ou d'une commande à un agent depuis les cartes.
 *
 * Jusqu'ici seul l'agent pouvait se saisir d'une demande depuis son téléphone.
 * Ces tests vérifient que l'attribution faite au comptoir laisse la base dans le
 * même état qu'une prise en charge faite depuis l'application — sans quoi on
 * obtient des courses incohérentes : un agent marqué libre alors qu'il roule, ou
 * une commission prélevée sur un solde absent.
 */
class AttributionAgentTest extends TestCase
{
    /** Agent dont le solde couvre largement n'importe quelle course. */
    private function agentSolvable(): Agent
    {
        $agent = Agent::whereNotNull('id_user')->get()
            ->first(fn (Agent $a) => (new Fonction())->solde($a->id_user)['solde'] > 50000);

        if (! $agent) {
            $this->markTestSkipped('Aucun agent avec un solde suffisant dans la base de développement.');
        }

        return $agent;
    }

    /** Agent au solde nul, pour éprouver le refus. */
    private function agentSansSolde(): Agent
    {
        $agent = Agent::whereNotNull('id_user')->get()
            ->first(fn (Agent $a) => (new Fonction())->solde($a->id_user)['solde'] <= 0);

        if (! $agent) {
            $this->markTestSkipped('Aucun agent au solde nul dans la base de développement.');
        }

        return $agent;
    }

    /** Course libre, prête à être attribuée. */
    private function courseLibre(int $commission = 100): Clando
    {
        $course = Clando::query()->firstOrFail();

        $course->update([
            'id_agent' => null,
            'status' => 'want',
            'delivery_type' => 'clando',
            'commission_agent' => $commission,
        ]);

        return $course->fresh();
    }

    public function test_une_course_est_attribuee_a_un_agent(): void
    {
        $agent = $this->agentSolvable();
        $course = $this->courseLibre();
        $etatCourse = $course->only(['id_agent', 'status', 'matricule_vehicule', 'commission_agent', 'delivery_type']);
        $freeStatus = $agent->freeStatus;

        $reponse = $this->postJson("/clando/{$course->id}/agent", ['id_agent' => $agent->id_user]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('attribution.ok'));

        $frais = $course->fresh();
        $this->assertSame((int) $agent->id_user, (int) $frais->id_agent);
        // Même état que takeClandoCommand : la course passe en cours et reprend
        // le matricule du véhicule de l'agent.
        $this->assertSame('process', $frais->status);
        $this->assertSame($agent->matricule_vehicule, $frais->matricule_vehicule);

        // L'agent n'est plus libre : c'est ce drapeau que consulte l'application
        // pour décider de lui proposer une course.
        $this->assertSame(0, (int) Agent::where('id_user', $agent->id_user)->value('freeStatus'));

        // La réponse porte la carte à jour : l'écran n'attend pas le cycle suivant.
        $reponse->assertJsonStructure(['courses', 'agents', 'agents_disponibles', 'stats']);

        Clando::where('id', $course->id)->update($etatCourse);
        Agent::where('id_user', $agent->id_user)->update(['freeStatus' => $freeStatus]);
    }

    public function test_une_course_deja_prise_ne_peut_pas_etre_reattribuee(): void
    {
        $agent = $this->agentSolvable();
        $course = $this->courseLibre();
        $etatCourse = $course->only(['id_agent', 'status', 'commission_agent', 'delivery_type']);

        // Quelqu'un l'a prise entre-temps, depuis un autre écran ou le téléphone.
        $course->update(['id_agent' => 999999]);

        $reponse = $this->postJson("/clando/{$course->id}/agent", ['id_agent' => $agent->id_user]);

        $reponse->assertStatus(409);
        $this->assertFalse($reponse->json('attribution.ok'));
        $this->assertSame(999999, (int) $course->fresh()->id_agent, 'L\'agent en place ne doit pas être écrasé.');

        Clando::where('id', $course->id)->update($etatCourse);
    }

    public function test_un_solde_insuffisant_refuse_l_attribution(): void
    {
        $agent = $this->agentSansSolde();
        // Commission hors de portée d'un solde nul.
        $course = $this->courseLibre(500000);
        $etatCourse = $course->only(['id_agent', 'status', 'commission_agent', 'delivery_type']);
        $freeStatus = $agent->freeStatus;

        $reponse = $this->postJson("/clando/{$course->id}/agent", ['id_agent' => $agent->id_user]);

        $reponse->assertStatus(422);
        $this->assertFalse($reponse->json('attribution.ok'));
        // Le message s'adresse à un opérateur, pas à l'agent : il doit nommer qui
        // et combien il manque.
        $this->assertStringContainsString('Solde insuffisant', $reponse->json('attribution.message'));

        $this->assertNull($course->fresh()->id_agent);
        // Un refus ne doit pas laisser l'agent marqué occupé.
        $this->assertSame(
            (int) $freeStatus,
            (int) Agent::where('id_user', $agent->id_user)->value('freeStatus'),
        );

        Clando::where('id', $course->id)->update($etatCourse);
    }

    public function test_un_utilisateur_qui_n_est_pas_agent_est_refuse(): void
    {
        $course = $this->courseLibre();
        $etatCourse = $course->only(['id_agent', 'status', 'commission_agent', 'delivery_type']);

        $reponse = $this->postJson("/clando/{$course->id}/agent", ['id_agent' => 987654]);

        $reponse->assertStatus(422);
        $this->assertStringContainsString("n'est pas un agent", $reponse->json('attribution.message'));
        $this->assertNull($course->fresh()->id_agent);

        Clando::where('id', $course->id)->update($etatCourse);
    }

    public function test_l_identifiant_d_agent_est_obligatoire(): void
    {
        $course = Clando::query()->firstOrFail();

        $this->postJson("/clando/{$course->id}/agent", [])->assertStatus(422);
    }

    public function test_une_commande_est_attribuee_a_un_livreur(): void
    {
        $agent = $this->agentSolvable();

        $commande = order_detail::query()->firstOrFail();
        $etat = $commande->only(['id_agent', 'status', 'matricule_vehicule', 'price']);
        $freeStatus = $agent->freeStatus;

        // takeOrderCommand contrôle le solde contre le prix de la commande, pas
        // contre une commission : on reste sous le solde de l'agent.
        $commande->update(['id_agent' => null, 'status' => 'want', 'price' => 1000]);

        $reponse = $this->postJson("/commandes/carte/{$commande->id}/agent", ['id_agent' => $agent->id_user]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('attribution.ok'));

        $frais = $commande->fresh();
        $this->assertSame((int) $agent->id_user, (int) $frais->id_agent);
        $this->assertSame('process', $frais->status);
        $this->assertSame(0, (int) Agent::where('id_user', $agent->id_user)->value('freeStatus'));

        order_detail::where('id', $commande->id)->update($etat);
        Agent::where('id_user', $agent->id_user)->update(['freeStatus' => $freeStatus]);
    }

    public function test_une_commande_deja_attribuee_est_refusee(): void
    {
        $agent = $this->agentSolvable();
        $commande = order_detail::query()->firstOrFail();
        $etat = $commande->only(['id_agent', 'status', 'price']);

        // order_details.id_agent porte une clé étrangère vers users : l'occupant
        // doit être un utilisateur réel, un identifiant inventé serait rejeté par
        // la base avant même d'atteindre le contrôleur.
        $occupant = \App\Models\User::where('id', '!=', $agent->id_user)->value('id');

        $commande->update(['id_agent' => $occupant, 'status' => 'want', 'price' => 1000]);

        $this->postJson("/commandes/carte/{$commande->id}/agent", ['id_agent' => $agent->id_user])
            ->assertStatus(409);

        $this->assertSame((int) $occupant, (int) $commande->fresh()->id_agent);

        order_detail::where('id', $commande->id)->update($etat);
    }

    public function test_la_liste_des_agents_distingue_service_et_disponibilite(): void
    {
        /*
         * Les agents occupés restent listés : les masquer ferait croire à un
         * opérateur qu'un agent n'existe pas alors qu'il est simplement en course.
         */
        $agents = $this->getJson('/clando/flux')->assertOk()->json('agents_disponibles');

        $this->assertNotEmpty($agents);

        foreach ($agents as $a) {
            $this->assertArrayHasKey('libre', $a);
            $this->assertArrayHasKey('en_service', $a);
            $this->assertIsBool($a['libre']);
            $this->assertIsBool($a['en_service']);
            $this->assertNotNull($a['id_user']);
        }
    }
}
