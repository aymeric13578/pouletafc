<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\User;
use Tests\TestCase;

/**
 * Carte des courses clando, en accès libre comme le mur des commandes.
 */
class ClandoBoardTest extends TestCase
{
    public function test_la_carte_s_affiche_pour_l_equipe(): void
    {
        $staff = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($staff)->get('/clando')->assertOk();
    }

    public function test_la_carte_est_accessible_sans_connexion(): void
    {
        /*
         * Accès libre voulu, comme /commandes : l'écran tourne dans le local sans
         * session ouverte. Conséquence assumée et plus lourde ici, la position en
         * direct des agents est visible de qui connaît l'URL.
         */
        $this->get('/clando')->assertOk();
        $this->getJson('/clando/flux')->assertOk();
    }

    public function test_le_flux_expose_ce_dont_la_carte_a_besoin(): void
    {
        $this->getJson('/clando/flux')
            ->assertOk()
            ->assertJsonStructure([
                'courses',
                'agents',
                'stats' => ['actives', 'en_attente', 'du_jour', 'ca_jour', 'agents_actifs'],
                'latest_id',
                'server_time',
                'server_date',
            ]);
    }

    public function test_le_flux_n_est_jamais_mis_en_cache(): void
    {
        /*
         * La carte reste ouverte des heures : un proxy qui mettrait ce flux en
         * cache figerait les agents sur une position périmée, ce qui trompe
         * davantage que l'absence de carte.
         */
        // Symfony réordonne les directives : on vérifie leur présence, pas la chaîne.
        $entete = $this->getJson('/clando/flux')->assertOk()->headers->get('Cache-Control');

        foreach (['no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $entete);
        }
    }

    public function test_une_coordonnee_nulle_ou_a_zero_ne_produit_pas_de_point(): void
    {
        /*
         * Les colonnes de position de clando sont des double nullables : restent
         * donc null et le zéro posé quand la position n'a pas été relevée. Un zéro
         * tombe au large du golfe de Guinée — laissé passer, il envoie le marqueur
         * à mille kilomètres de Douala et fait dézoomer la carte sur l'Atlantique.
         */
        $course = Clando::query()->firstOrFail();
        $original = $course->only(['latMyPosition', 'lonMyPosition', 'status', 'delivery_type']);

        $course->update([
            'latMyPosition' => 0,
            'lonMyPosition' => null,
            'status' => 'want',
            'delivery_type' => 'clando',
        ]);

        $charge = $this->getJson('/clando/flux')->assertOk()->json();
        $trouvee = collect($charge['courses'])->firstWhere('id', $course->id);

        $this->assertNotNull($trouvee, 'La course doit rester listée même sans coordonnées exploitables.');
        $this->assertNull($trouvee['depart'], 'Un « 0 » ou une chaîne vide ne doit pas devenir un point sur la carte.');

        $course->update($original);
    }

    public function test_une_course_sans_agent_est_signalee_comme_en_attente(): void
    {
        $course = Clando::query()->firstOrFail();
        $original = $course->only(['status', 'delivery_type']);

        $course->update(['status' => 'want', 'delivery_type' => 'clando']);

        $charge = $this->getJson('/clando/flux')->assertOk()->json();
        $trouvee = collect($charge['courses'])->firstWhere('id', $course->id);

        $this->assertNotNull($trouvee);
        $this->assertTrue($trouvee['en_attente'], "C'est ce drapeau qui déclenche l'alerte sonore.");
        $this->assertTrue($trouvee['active']);
        $this->assertSame('Demandée', $trouvee['status_label']);

        $course->update($original);
    }

    public function test_les_courses_de_coursier_sont_exclues(): void
    {
        /*
         * Le critère est delivery_type, comme sur l'écran Clando du tableau de bord :
         * c'est le seul champ qui sépare réellement les deux services.
         */
        $course = Clando::query()->firstOrFail();
        $original = $course->only(['delivery_type', 'status']);

        $course->update(['delivery_type' => 'delivery', 'status' => 'want']);

        $charge = $this->getJson('/clando/flux')->assertOk()->json();

        $this->assertNull(
            collect($charge['courses'])->firstWhere('id', $course->id),
            'Une course de coursier ne doit pas apparaître sur la carte clando.',
        );

        $course->update($original);
    }

    public function test_un_agent_sans_position_n_est_pas_place_sur_la_carte(): void
    {
        $charge = $this->getJson('/clando/flux')->assertOk()->json();

        foreach ($charge['agents'] as $agent) {
            $this->assertNotNull($agent['lat']);
            $this->assertNotNull($agent['lon']);
        }
    }

    public function test_un_agent_en_course_est_marque_comme_suivi_en_direct(): void
    {
        /*
         * Distinction essentielle : pendant une course, la position est poussée
         * toutes les trois secondes ; hors course, elle date du démarrage de la
         * journée et ne bougera plus. La carte doit le dire plutôt que de laisser
         * croire à un suivi continu.
         */
        $agent = User::where('role', 'agent')->firstOrFail();
        $etatAgent = $agent->only(['in_activity', 'actual_lat_position_agent', 'actual_lon_position_agent']);

        $course = Clando::query()->firstOrFail();
        $etatCourse = $course->only(['id_agent', 'status', 'delivery_type', 'latAgent', 'lonAgent']);

        $agent->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => '4.0500',
            'actual_lon_position_agent' => '9.7000',
        ]);

        $course->update([
            'id_agent' => $agent->id,
            'status' => 'process',
            'delivery_type' => 'clando',
            'latAgent' => '4.0611',
            'lonAgent' => '9.7779',
        ]);

        $charge = $this->getJson('/clando/flux')->assertOk()->json();
        $trouve = collect($charge['agents'])->firstWhere('id', $agent->id);

        $this->assertNotNull($trouve);
        $this->assertTrue($trouve['frais'], 'Un agent en course est suivi en direct.');
        // La position affichée est celle poussée par la course, pas celle du
        // démarrage de journée.
        $this->assertEqualsWithDelta(4.0611, $trouve['lat'], 0.0001);
        $this->assertEqualsWithDelta(9.7779, $trouve['lon'], 0.0001);

        $course->update($etatCourse);
        $agent->update($etatAgent);
    }

    public function test_un_agent_hors_course_porte_son_dernier_point_connu(): void
    {
        $agent = User::where('role', 'agent')->firstOrFail();
        $etatAgent = $agent->only(['in_activity', 'actual_lat_position_agent', 'actual_lon_position_agent']);

        // On s'assure qu'aucune course active ne lui est rattachée.
        $coursesActives = Clando::where('id_agent', $agent->id)
            ->whereIn('status', ['pending', 'want', 'take', 'process'])
            ->get();
        $etatCourses = $coursesActives->map(fn ($c) => [$c->id, $c->status])->all();
        foreach ($coursesActives as $c) {
            $c->update(['status' => 'Success']);
        }

        $agent->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => '4.0500',
            'actual_lon_position_agent' => '9.7000',
        ]);

        $charge = $this->getJson('/clando/flux')->assertOk()->json();
        $trouve = collect($charge['agents'])->firstWhere('id', $agent->id);

        $this->assertNotNull($trouve);
        $this->assertFalse($trouve['frais'], "Hors course, la position n'est plus rafraîchie.");
        $this->assertEqualsWithDelta(4.0500, $trouve['lat'], 0.0001);

        foreach ($etatCourses as [$id, $statut]) {
            Clando::where('id', $id)->update(['status' => $statut]);
        }
        $agent->update($etatAgent);
    }

    public function test_latest_id_ignore_les_filtres_d_affichage(): void
    {
        /*
         * L'alerte se fonde sur latest_id. Le calculer sur les seules courses
         * affichées raterait une demande arrivée pendant qu'un filtre est actif.
         */
        $charge = $this->getJson('/clando/flux')->assertOk()->json();

        $attendu = (int) Clando::query()
            ->where(function ($q) {
                $q->whereNull('delivery_type')->orWhere('delivery_type', '!=', 'delivery');
            })
            ->max('id');

        $this->assertSame($attendu, $charge['latest_id']);
    }
}
