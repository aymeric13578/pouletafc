<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Position des agents sur les cartes du tableau de bord.
 *
 * Elle n'était écrite qu'une fois, au démarrage de la journée par takeDay, puis
 * plus jamais hors d'une course — et seulement depuis l'écran de cette course.
 * Un agent en service mais sans course restait donc figé à son point du matin :
 * en base au moment d'écrire ce test, trois agents « en service » portaient des
 * positions datant du 4 juillet, du 5 juillet et du 5 août.
 */
class PositionAgentTest extends TestCase
{
    private const URL = '/api/v1.0/updateAgentPosition';

    private function agent(): User
    {
        $agent = User::where('role', 'agent')->first();

        if (! $agent) {
            $this->markTestSkipped('Aucun agent dans la base de développement.');
        }

        return $agent;
    }

    private function etat(User $u): array
    {
        return [
            'actual_lat_position_agent' => $u->actual_lat_position_agent,
            'actual_lon_position_agent' => $u->actual_lon_position_agent,
            'position_updated_at' => $u->position_updated_at,
            'in_activity' => $u->in_activity,
        ];
    }

    private function restaurer(User $u, array $etat): void
    {
        DB::table('users')->where('id', $u->id)->update($etat);
    }

    public function test_la_position_transmise_est_enregistree_avec_son_horodatage(): void
    {
        $agent = $this->agent();
        $etat = $this->etat($agent);

        $this->getJson(self::URL . "?id_user={$agent->id}&lat=9.3050&lon=13.3950")
            ->assertOk()
            ->assertJson(['response' => 200]);

        $frais = $agent->fresh();

        $this->assertEqualsWithDelta(9.3050, (float) $frais->actual_lat_position_agent, 0.0001);
        $this->assertEqualsWithDelta(13.3950, (float) $frais->actual_lon_position_agent, 0.0001);
        // Sans horodatage, les cartes ne peuvent pas distinguer un relevé de dix
        // secondes d'un relevé de six semaines.
        $this->assertNotNull($frais->position_updated_at);
        $this->assertTrue($frais->position_updated_at->greaterThan(now()->subMinute()));

        $this->restaurer($agent, $etat);
    }

    public function test_une_coordonnee_nulle_est_refusee(): void
    {
        /*
         * Un zéro tombe au large du golfe de Guinée : l'écrire ferait sauter le
         * marqueur à mille kilomètres de Garoua.
         */
        $agent = $this->agent();
        $etat = $this->etat($agent);

        DB::table('users')->where('id', $agent->id)->update([
            'actual_lat_position_agent' => 9.3000,
            'actual_lon_position_agent' => 13.4000,
        ]);

        $this->getJson(self::URL . "?id_user={$agent->id}&lat=0&lon=0")
            ->assertOk()
            ->assertJson(['response' => 400]);

        $this->assertEqualsWithDelta(9.3000, (float) $agent->fresh()->actual_lat_position_agent, 0.0001);

        $this->restaurer($agent, $etat);
    }

    public function test_une_requete_incomplete_ne_touche_a_rien(): void
    {
        $agent = $this->agent();

        $this->getJson(self::URL)->assertOk()->assertJson(['response' => 400]);
        $this->getJson(self::URL . "?id_user={$agent->id}")->assertOk()->assertJson(['response' => 400]);
        $this->getJson(self::URL . "?id_user={$agent->id}&lat=abc&lon=13.4")
            ->assertOk()->assertJson(['response' => 400]);
    }

    public function test_une_position_recente_est_donnee_pour_du_direct(): void
    {
        $agent = $this->agent();
        $etat = $this->etat($agent);

        DB::table('users')->where('id', $agent->id)->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => 9.3050,
            'actual_lon_position_agent' => 13.3950,
            'position_updated_at' => now(),
        ]);

        $agents = $this->getJson('/clando/flux')->assertOk()->json('agents');
        $trouve = collect($agents)->firstWhere('id', $agent->id);

        $this->assertNotNull($trouve, "L'agent en service doit figurer sur la carte.");
        $this->assertTrue($trouve['frais'], 'Un relevé de maintenant vaut du direct.');

        $this->restaurer($agent, $etat);
    }

    public function test_une_position_dormante_n_est_pas_presentee_comme_du_direct(): void
    {
        /*
         * C'est le cœur du problème signalé : des agents affichés comme suivis
         * alors que leur position datait de plusieurs semaines.
         */
        $agent = $this->agent();
        $etat = $this->etat($agent);

        DB::table('users')->where('id', $agent->id)->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => 9.3050,
            'actual_lon_position_agent' => 13.3950,
            'position_updated_at' => now()->subWeeks(6),
        ]);

        $agents = $this->getJson('/clando/flux')->assertOk()->json('agents');
        $trouve = collect($agents)->firstWhere('id', $agent->id);

        $this->assertNotNull($trouve, "L'agent reste affiché, mais pas comme suivi.");
        $this->assertFalse($trouve['frais'], 'Une position de six semaines ne vaut pas du direct.');
        // La date du relevé accompagne le point : l'opérateur doit pouvoir juger.
        $this->assertNotNull($trouve['position_datee']);

        $this->restaurer($agent, $etat);
    }

    public function test_la_carte_des_livraisons_applique_la_meme_regle(): void
    {
        $agent = $this->agent();
        $etat = $this->etat($agent);

        DB::table('users')->where('id', $agent->id)->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => 9.3050,
            'actual_lon_position_agent' => 13.3950,
            'position_updated_at' => now()->subHours(3),
        ]);

        $agents = $this->getJson('/commandes/carte/flux')->assertOk()->json('agents');
        $trouve = collect($agents)->firstWhere('id', $agent->id);

        $this->assertNotNull($trouve);
        $this->assertFalse($trouve['frais']);

        $this->restaurer($agent, $etat);
    }

    public function test_deux_envois_successifs_font_bouger_le_marqueur(): void
    {
        // Le point du problème : c'est le mouvement qui manquait, pas le point.
        $agent = $this->agent();
        $etat = $this->etat($agent);

        $this->getJson(self::URL . "?id_user={$agent->id}&lat=9.3000&lon=13.4000")->assertOk();
        $premier = $agent->fresh()->actual_lat_position_agent;

        $this->getJson(self::URL . "?id_user={$agent->id}&lat=9.3100&lon=13.4100")->assertOk();
        $second = $agent->fresh()->actual_lat_position_agent;

        $this->assertNotEquals($premier, $second, 'La position doit suivre chaque envoi.');
        $this->assertEqualsWithDelta(9.3100, (float) $second, 0.0001);

        $this->restaurer($agent, $etat);
    }
}
