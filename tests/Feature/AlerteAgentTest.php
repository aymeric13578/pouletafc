<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ce qui fait sonner le téléphone d'un agent.
 *
 * getActiveCommand est le seul déclencheur : l'application l'interroge en boucle
 * et ouvre une fenêtre sonore dès qu'il renvoie quelque chose.
 *
 * « want » veut dire « à enlever », et c'est ce que pose le bouton « Colis prêt »
 * du mur. La sonnerie part donc après le geste du comptoir, jamais à la prise de
 * commande : une commande fraîche reste en « pending ».
 *
 * S'y ajoute une borne du jour : sans elle, une ligne oubliée dans ce statut
 * sonnait sur tous les téléphones à chaque redémarrage de l'application, sa
 * déduplication ne tenant qu'en mémoire.
 */
class AlerteAgentTest extends TestCase
{
    private const URL = '/api/v1.0/getActiveCommand';

    /** Agent que l'endpoint accepte : il exige freeStatus et in_activity à 1. */
    private function agentDisponible(): array
    {
        $agent = Agent::whereNotNull('id_user')->first();

        if (! $agent) {
            $this->markTestSkipped('Aucun agent dans la base de développement.');
        }

        $etat = ['freeStatus' => $agent->freeStatus, 'in_activity' => $agent->in_activity];
        DB::table('agents')->where('id', $agent->id)->update(['freeStatus' => 1, 'in_activity' => 1]);

        return [$agent->id_user, $agent->id, $etat];
    }

    private function restaurerAgent(int $id, array $etat): void
    {
        DB::table('agents')->where('id', $id)->update($etat);
    }

    private function etatCommande(order_detail $c): array
    {
        return ['status' => $c->status, 'id_agent' => $c->id_agent, 'created_at' => $c->created_at];
    }

    public function test_une_commande_qui_vient_d_etre_passee_ne_sonne_pas(): void
    {
        /*
         * C'est le point signalé : passer commande ne doit réveiller personne.
         * Le colis n'est pas préparé, l'agent n'a rien à venir chercher.
         */
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $this->getJson(self::URL . '?id_user=' . $idUser)
            ->assertOk()
            ->assertJson(['response' => 400]);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_colis_pret_fait_sonner(): void
    {
        // Le geste du comptoir pose « want » : c'est lui qui alerte les agents.
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $charge = $this->getJson(self::URL . '?id_user=' . $idUser)->assertOk()->json();

        $this->assertSame(200, $charge['response']);
        $this->assertNotNull($charge['order_detail'] ?? null);
        $this->assertSame($commande->ref, $charge['order_detail']['ref']);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_le_bouton_du_mur_declenche_reellement_la_sonnerie(): void
    {
        /*
         * Chaîne complète plutôt que statut posé à la main : le bouton
         * « Colis prêt » du mur passe par cet endpoint, et c'est ce statut-là
         * qui doit réveiller les agents. Poser la valeur directement en base
         * testerait ma propre hypothèse, pas le geste du comptoir.
         */
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        // Rien ne sonne tant que le comptoir n'a pas agi.
        $this->getJson(self::URL . '?id_user=' . $idUser)->assertOk()->assertJson(['response' => 400]);

        $this->postJson("/commandes/{$commande->id}/statut", ['status' => 'want'])->assertOk();

        $this->assertSame('want', $commande->fresh()->status);

        $charge = $this->getJson(self::URL . '?id_user=' . $idUser)->assertOk()->json();
        $this->assertSame(200, $charge['response']);
        $this->assertSame($commande->ref, $charge['order_detail']['ref']);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_une_commande_deja_prise_ne_sonne_pas(): void
    {
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);
        $occupant = \App\Models\User::query()->value('id');

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'want',
            'id_agent' => $occupant,
            'created_at' => now(),
        ]);

        $this->getJson(self::URL . '?id_user=' . $idUser)
            ->assertOk()
            ->assertJson(['response' => 400]);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_un_identifiant_qui_n_est_pas_un_agent_ne_fait_pas_planter_le_serveur(): void
    {
        /*
         * Le test du compte agent commençait à « $agent->freeStatus » sans
         * vérifier que la recherche avait trouvé quelque chose : un identifiant
         * qui n'est pas un agent faisait répondre 500, une panne serveur là où
         * la réponse attendue est « rien pour vous ».
         */
        $this->getJson(self::URL . '?id_user=999999')
            ->assertOk()
            ->assertJson(['response' => 400]);

        $this->getJson(self::URL)->assertOk()->assertJson(['response' => 400]);
    }

    public function test_un_colis_pret_d_hier_ne_sonne_plus(): void
    {
        /*
         * La déduplication de l'application ne tient qu'en mémoire : sans borne
         * de date, une ligne oubliée resonnait sur tous les téléphones à chaque
         * lancement, indéfiniment.
         */
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now()->subDays(3),
        ]);

        $this->getJson(self::URL . '?id_user=' . $idUser)
            ->assertOk()
            ->assertJson(['response' => 400]);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_une_demande_de_course_sonne_immediatement(): void
    {
        /*
         * Les courses gardent « want » : une demande de course doit atteindre les
         * agents dès qu'elle est passée, c'est tout l'objet du service. Le
         * changement ne concerne que les commandes.
         */
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = ['status' => $course->status, 'id_agent' => $course->id_agent, 'created_at' => $course->created_at];

        /*
         * L'endpoint tait volontairement une course que cet agent a déjà
         * refusée. On efface ces refus le temps du test, puis on les repose :
         * sinon le résultat dépendrait de l'historique de la base de dev.
         */
        $refus = DB::table('declin_command')
            ->where('id_user', $idUser)->where('id_clando', $course->id)->get();
        DB::table('declin_command')
            ->where('id_user', $idUser)->where('id_clando', $course->id)->delete();

        DB::table('clando')->where('id', $course->id)->update([
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $charge = $this->getJson(self::URL . '?id_user=' . $idUser)->assertOk()->json();

        $this->assertSame(200, $charge['response']);
        $this->assertNotNull($charge['data'] ?? null);
        $this->assertSame($course->ref, $charge['data']['ref']);

        DB::table('clando')->where('id', $course->id)->update($etat);

        foreach ($refus as $ligne) {
            DB::table('declin_command')->insert((array) $ligne);
        }

        $this->restaurerAgent($idAgent, $etatAgent);
    }

    public function test_une_demande_de_course_ne_choisit_plus_son_statut_par_defaut(): void
    {
        /*
         * insertclando écrivait 'status' deux fois dans le même tableau :
         * 'pending', puis $request->status. En PHP la seconde l'emporte, donc la
         * première n'a jamais rien fait. Sans statut transmis, la course partait
         * avec une valeur nulle.
         */
        $reponse = $this->getJson('/api/v1.0/insertclando?' . http_build_query([
            // Le compte doit être validé : l'endpoint refuse les autres.
            'id_user' => \App\Models\User::where('status', 'Success')->value('id'),
            'latMyPosition' => 9.30,
            'lonMyPosition' => 13.39,
            'latDestination' => 9.31,
            'lonDestination' => 13.40,
            'price' => 1000,
            'times' => '10 min',
            'distance' => 2.5,
            'destinationName' => 'Test reprise statut',
            // enum('classic','vip') : toute autre valeur est rejetée par MySQL.
            'type' => 'classic',
        ]));

        $reponse->assertOk();

        $creee = Clando::where('destinationName', 'Test reprise statut')->orderByDesc('id')->first();

        if ($creee) {
            $this->assertSame('want', $creee->status, 'Une demande sans statut doit partir en « want ».');
            $creee->delete();
        }
    }
}
