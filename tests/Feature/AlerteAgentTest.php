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
 * Il guettait les commandes en « want ». Or rien dans l'API ne pose ce statut
 * sur une commande — « Colis prêt » pose « waiting ». La sonnerie censée
 * prévenir qu'un colis attend ne partait donc jamais, tandis que la moindre
 * ligne oubliée en « want » sonnait sur tous les téléphones à chaque
 * redémarrage de l'application.
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

    public function test_colis_pret_fait_enfin_sonner(): void
    {
        /*
         * L'inverse du défaut précédent : le geste censé alerter les agents
         * n'alertait personne, puisque l'endpoint guettait « want » quand le
         * bouton pose « waiting ».
         */
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'waiting',
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

    public function test_une_commande_deja_prise_ne_sonne_pas(): void
    {
        [$idUser, $idAgent, $etatAgent] = $this->agentDisponible();

        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = $this->etatCommande($commande);
        $occupant = \App\Models\User::query()->value('id');

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'waiting',
            'id_agent' => $occupant,
            'created_at' => now(),
        ]);

        $this->getJson(self::URL . '?id_user=' . $idUser)
            ->assertOk()
            ->assertJson(['response' => 400]);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        $this->restaurerAgent($idAgent, $etatAgent);
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
            'status' => 'waiting',
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
