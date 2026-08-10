<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reprise du suivi après fermeture de l'application.
 *
 * Fermer l'application faisait perdre l'écran de suivi alors que la course
 * continuait côté serveur : le client ne voyait plus l'agent approcher, et
 * l'agent ne renvoyait plus sa position. Cet endpoint dit à chaque application
 * où ramener l'utilisateur.
 */
class RepriseSuiviTest extends TestCase
{
    private const URL = '/api/v1.0/getCourseEnCours';

    /** created_at n'est pas dans $fillable : il faut passer par le query builder. */
    private function ecrire(string $table, int $id, array $valeurs): void
    {
        DB::table($table)->where('id', $id)->update($valeurs);
    }

    private function etatClando(Clando $c): array
    {
        return [
            'status' => $c->status,
            'id_agent' => $c->id_agent,
            'created_at' => $c->created_at,
        ];
    }

    public function test_sans_identifiant_la_reponse_est_explicite(): void
    {
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJson(['response' => 400, 'data' => null]);
    }

    public function test_un_utilisateur_sans_course_ne_recoit_rien(): void
    {
        // null est une réponse normale, pas une erreur : la plupart du temps il
        // n'y a rien à reprendre.
        $this->getJson(self::URL . '?id_user=999999')
            ->assertOk()
            ->assertJson(['response' => 200, 'data' => null]);
    }

    public function test_l_agent_retrouve_sa_course_en_cours(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=173')->assertOk()->json('data');

        $this->assertNotNull($data);
        $this->assertSame('clando', $data['type']);
        $this->assertSame('agent', $data['role']);
        $this->assertSame($course->ref, $data['ref']);
        // La carte de suivi, pas l'écran d'attente : un agent est assigné.
        $this->assertSame('suivi', $data['ecran']);

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_le_client_retrouve_la_meme_course(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=' . $course->id_user)->assertOk()->json('data');

        $this->assertNotNull($data);
        $this->assertSame('client', $data['role']);
        $this->assertSame($course->ref, $data['ref']);

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_sans_agent_le_client_revient_sur_l_ecran_d_attente(): void
    {
        /*
         * Tant que personne n'a pris la course, il n'y a personne à suivre : la
         * carte afficherait un agent immobile à l'origine des coordonnées.
         */
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=' . $course->id_user)->assertOk()->json('data');

        $this->assertNotNull($data);
        $this->assertSame('attente', $data['ecran']);
        $this->assertNull($data['agent']);

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_une_course_terminee_n_est_pas_reprise(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'Success',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $this->assertNull(
            $this->getJson(self::URL . '?id_user=173')->assertOk()->json('data'),
            'Une course terminée ne doit pas rouvrir une carte.',
        );

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_une_course_abandonnee_depuis_longtemps_n_est_pas_reprise(): void
    {
        /*
         * Une demande restée « want » depuis des jours n'a pas été interrompue,
         * elle a été abandonnée sans que personne ne la close. Proposer d'y
         * revenir rouvrirait une carte sur une course morte.
         */
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now()->subHours(30),
        ]);

        $this->assertNull($this->getJson(self::URL . '?id_user=173')->assertOk()->json('data'));

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_le_travail_en_cours_passe_avant_la_commande_personnelle(): void
    {
        /*
         * Un même compte peut être des deux côtés. C'est la carte de l'agent qui
         * renvoie sa position au serveur : le ramener sur sa propre commande
         * couperait ce suivi.
         */
        $courseAgent = Clando::orderByDesc('id')->firstOrFail();
        $courseClient = Clando::where('id', '!=', $courseAgent->id)->orderByDesc('id')->firstOrFail();

        $etatAgent = $this->etatClando($courseAgent);
        $etatClient = $this->etatClando($courseClient);
        $idUserClient = $courseClient->id_user;

        // Le même utilisateur est agent sur l'une, client sur l'autre.
        $this->ecrire('clando', $courseAgent->id, [
            'status' => 'process',
            'id_agent' => $idUserClient,
            'created_at' => now(),
        ]);
        $this->ecrire('clando', $courseClient->id, [
            'status' => 'want',
            'id_agent' => null,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=' . $idUserClient)->assertOk()->json('data');

        $this->assertSame('agent', $data['role']);
        $this->assertSame($courseAgent->ref, $data['ref']);

        $this->ecrire('clando', $courseAgent->id, $etatAgent);
        $this->ecrire('clando', $courseClient->id, $etatClient);
    }

    public function test_la_charge_porte_tout_ce_qu_attend_l_ecran(): void
    {
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);

        $this->ecrire('clando', $course->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=173')->assertOk()->json('data');

        // Ces noms sont ceux qu'attendent les constructeurs Flutter : une reprise
        // doit reconstruire l'écran quitté, pas une approximation.
        foreach (['type', 'role', 'ref', 'status', 'ecran', 'lat', 'lon', 'destination',
            'distance', 'times', 'price', 'prise_en_charge'] as $champ) {
            $this->assertArrayHasKey($champ, $data);
        }

        $this->ecrire('clando', $course->id, $etat);
    }

    public function test_une_livraison_en_cours_est_reprise(): void
    {
        $commande = order_detail::orderByDesc('id')->firstOrFail();
        $etat = [
            'status' => $commande->status,
            'id_agent' => $commande->id_agent,
            'created_at' => $commande->created_at,
        ];

        $this->ecrire('order_details', $commande->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now(),
        ]);

        $data = $this->getJson(self::URL . '?id_user=173')->assertOk()->json('data');

        $this->assertNotNull($data);
        $this->assertSame('order', $data['type']);
        $this->assertSame($commande->ref, $data['ref']);
        // Pour une livraison, le point de départ est la boutique et non le
        // client : c'est de là que part l'agent, et l'écran affiche ce nom.
        $this->assertSame(
            $commande->shop_name ?? 'Point de départ inconnu',
            $data['prise_en_charge'],
        );
        $this->assertSame($commande->address ?? 'Destination inconnue', $data['destination']);

        $this->ecrire('order_details', $commande->id, $etat);
    }

    public function test_une_coordonnee_a_zero_ne_devient_pas_un_point(): void
    {
        // Un zéro tombe au large du golfe de Guinée : la carte s'ouvrirait à
        // mille kilomètres de la course.
        $course = Clando::orderByDesc('id')->firstOrFail();
        $etat = $this->etatClando($course);
        $positions = ['latMyPosition' => $course->latMyPosition, 'lonMyPosition' => $course->lonMyPosition];

        $this->ecrire('clando', $course->id, [
            'status' => 'process',
            'id_agent' => 173,
            'created_at' => now(),
            'latMyPosition' => 0,
            'lonMyPosition' => 0,
        ]);

        $data = $this->getJson(self::URL . '?id_user=173')->assertOk()->json('data');

        $this->assertNull($data['lat']);
        $this->assertNull($data['lon']);

        $this->ecrire('clando', $course->id, $etat + $positions);
    }
}
