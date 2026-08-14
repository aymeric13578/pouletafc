<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\order_detail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prévenir le client dès qu'un agent prend sa commande.
 *
 * Le client passait commande puis n'entendait plus parler de rien : il devait
 * rouvrir l'application et deviner. L'application agent, elle, sonne depuis
 * toujours à l'arrivée d'une commande. C'est la même mécanique, retournée vers
 * le client — et elle repose sur cet endpoint, qui dit tout ce que le client a
 * en cours et qui l'a pris.
 */
class NotificationPriseEnChargeTest extends TestCase
{
    private const URL = '/api/v1.0/getSuivisClient';

    /** created_at et id_agent ne sont pas tous fillable : on écrit en direct. */
    private function ecrire(string $table, int $id, array $valeurs): void
    {
        DB::table($table)->where('id', $id)->update($valeurs);
    }

    private function etat(string $table, int $id): array
    {
        return (array) DB::table($table)->where('id', $id)->first(
            ['status', 'id_agent', 'created_at']
        );
    }

    public function test_sans_identifiant_la_reponse_est_explicite(): void
    {
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJson(['response' => 400, 'data' => []]);
    }

    public function test_un_client_sans_rien_en_cours_recoit_une_liste_vide(): void
    {
        // Liste vide et non erreur : c'est le cas courant, l'application ne doit
        // pas le traiter comme une panne et cesser d'interroger.
        $this->getJson(self::URL . '?id_user=999999')
            ->assertOk()
            ->assertJson(['response' => 200, 'data' => []]);
    }

    /*
     | Le passage qui déclenche la sonnerie.
     |
     | Tant que personne n'a pris la commande, l'écran à rouvrir est l'attente.
     | Dès qu'un agent est attribué, c'est le suivi — et c'est ce basculement,
     | et lui seul, que l'application traduit en notification.
     */
    public function test_la_commande_passe_de_l_attente_au_suivi_quand_un_agent_la_prend(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $initial = $this->etat('order_details', $commande->id);
        $agent = User::whereNotNull('actual_lat_position_agent')->firstOrFail();

        try {
            $this->ecrire('order_details', $commande->id, [
                'status' => 'want',
                'id_agent' => null,
                'created_at' => now()->subMinutes(5),
            ]);

            $avant = collect($this->getJson(self::URL . '?id_user=' . $commande->id_user)->json('data'))
                ->firstWhere('ref', $commande->ref);

            $this->assertNotNull($avant, 'Une commande en attente doit être listée.');
            $this->assertSame('attente', $avant['ecran']);
            $this->assertNull($avant['agent'], 'Personne ne l\'a encore prise.');

            $this->ecrire('order_details', $commande->id, [
                'id_agent' => $agent->id,
                'status' => 'take',
            ]);

            $apres = collect($this->getJson(self::URL . '?id_user=' . $commande->id_user)->json('data'))
                ->firstWhere('ref', $commande->ref);

            $this->assertSame('suivi', $apres['ecran']);
            $this->assertNotNull($apres['agent'], 'Le client doit savoir qui vient.');
            $this->assertSame($agent->name, $apres['agent']['name']);
            $this->assertNotNull($apres['agent']['phone'], 'Il doit pouvoir l\'appeler.');
        } finally {
            $this->ecrire('order_details', $commande->id, $initial);
        }
    }

    /*
     | La position voyage avec le nom.
     |
     | Sans elle, l'écran ouvert depuis la notification afficherait un agent
     | nommé mais introuvable sur la carte le temps d'un second appel.
     */
    public function test_la_fiche_agent_porte_sa_position_et_sa_fraicheur(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $initial = $this->etat('order_details', $commande->id);
        $agent = User::whereNotNull('actual_lat_position_agent')
            ->whereNotNull('position_updated_at')
            ->firstOrFail();

        try {
            $this->ecrire('order_details', $commande->id, [
                'status' => 'take',
                'id_agent' => $agent->id,
                'created_at' => now()->subMinutes(5),
            ]);

            $fiche = collect($this->getJson(self::URL . '?id_user=' . $commande->id_user)->json('data'))
                ->firstWhere('ref', $commande->ref)['agent'];

            $this->assertEqualsWithDelta((float) $agent->actual_lat_position_agent, $fiche['lat'], 0.000001);
            $this->assertEqualsWithDelta((float) $agent->actual_lon_position_agent, $fiche['lon'], 0.000001);
            $this->assertNotNull($fiche['position_datee'], 'La carte doit pouvoir juger si la position est fraîche.');
        } finally {
            $this->ecrire('order_details', $commande->id, $initial);
        }
    }

    /*
     | Deux commandes en même temps.
     |
     | getCourseEnCours n'en renvoie qu'une : si une deuxième commande était
     | prise pendant qu'une première tournait, le client n'entendrait jamais
     | rien pour elle. C'est la raison d'être de cet endpoint.
     */
    public function test_deux_commandes_simultanees_sont_toutes_les_deux_listees(): void
    {
        $commandes = order_detail::orderByDesc('id')->take(2)->get();

        if ($commandes->count() < 2) {
            $this->markTestSkipped('Deux commandes sont nécessaires.');
        }

        $initial = $commandes->mapWithKeys(
            fn ($c) => [$c->id => $this->etat('order_details', $c->id)]
        );
        $client = $commandes->first()->id_user;

        try {
            foreach ($commandes as $commande) {
                $this->ecrire('order_details', $commande->id, [
                    'status' => 'take',
                    'id_user' => $client,
                    'created_at' => now()->subMinutes(5),
                ]);
            }

            $refs = collect($this->getJson(self::URL . '?id_user=' . $client)->json('data'))
                ->pluck('ref');

            foreach ($commandes as $commande) {
                $this->assertContains($commande->ref, $refs);
            }
        } finally {
            foreach ($initial as $id => $valeurs) {
                $this->ecrire('order_details', $id, $valeurs + ['id_user' => $commandes->firstWhere('id', $id)->id_user]);
            }
        }
    }

    /*
     | Une commande de la semaine dernière n'a pas été interrompue : elle a été
     | abandonnée. Sonner pour elle réveillerait le client sans raison.
     */
    public function test_une_commande_trop_ancienne_ne_declenche_rien(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $initial = $this->etat('order_details', $commande->id);

        try {
            $this->ecrire('order_details', $commande->id, [
                'status' => 'take',
                'created_at' => now()->subDays(3),
            ]);

            $refs = collect($this->getJson(self::URL . '?id_user=' . $commande->id_user)->json('data'))
                ->pluck('ref');

            $this->assertNotContains($commande->ref, $refs);
        } finally {
            $this->ecrire('order_details', $commande->id, $initial);
        }
    }

    /** Les courses clando du client sont listées au même titre. */
    public function test_les_courses_clando_sont_listees_avec_les_commandes(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $initial = $this->etat('clando', $course->id);

        try {
            $this->ecrire('clando', $course->id, [
                'status' => 'take',
                'created_at' => now()->subMinutes(5),
            ]);

            $entree = collect($this->getJson(self::URL . '?id_user=' . $course->id_user)->json('data'))
                ->firstWhere('ref', $course->ref);

            $this->assertNotNull($entree);
            $this->assertSame('clando', $entree['type']);
        } finally {
            $this->ecrire('clando', $course->id, $initial);
        }
    }
}
