<?php

namespace Tests\Feature;

use App\Models\GoalCampaign;
use App\Models\order_detail;
use App\Models\User;
use App\Support\ObjectifProgression;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Objectifs et primes — écran agent (GoalController) et tableau de bord
 * (dashboard/objectifs.blade.php).
 *
 * Le calcul de progression (App\Support\ObjectifProgression) est partagé
 * entre les deux : ce test le vérifie une fois, contre de vraies courses déjà
 * en base plutôt que des fixtures fabriquées — order_details a trop de
 * colonnes NOT NULL propres au flux de commande pour les insérer sans
 * reproduire toute la logique de CoursierController, et une course
 * fabriquée sans y ressembler ne prouverait rien.
 */
class ObjectifsEtPrimesTest extends TestCase
{
    private ?GoalCampaign $campagne = null;

    private function admin(): User
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        return $admin;
    }

    /** Un agent ayant réellement au moins une course livrée avec succès. */
    private function agentAvecCoursesReelles(): int
    {
        $idAgent = order_detail::where('status', 'Success')->whereNotNull('id_agent')->value('id_agent');

        if (! $idAgent) {
            $this->markTestSkipped('Aucune course Success en base pour construire ce test.');
        }

        return (int) $idAgent;
    }

    protected function tearDown(): void
    {
        // cascadeOnDelete sur goal_options/goal_enrollments/goal_progress.
        $this->campagne?->delete();
        parent::tearDown();
    }

    public function test_le_tableau_de_bord_objectifs_est_accessible_a_un_administrateur(): void
    {
        $this->campagne = GoalCampaign::create([
            'title' => 'Test — visible au tableau de bord',
            'metric' => 'rides',
            'ride_kind' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'enrollment_closes_at' => now()->addDays(7),
            'status' => 'running',
        ]);
        $this->campagne->options()->create(['label' => '5 courses', 'threshold' => 5, 'reward' => 1000, 'position' => 1]);

        $this->actingAs($this->admin())
            ->get('/dashboard/objectifs')
            ->assertOk()
            ->assertSee('Test — visible au tableau de bord');
    }

    public function test_getGoalCampaigns_renvoie_les_campagnes_en_cours_avec_leurs_options(): void
    {
        $this->campagne = GoalCampaign::create([
            'title' => 'Test — API agent',
            'metric' => 'rides',
            'ride_kind' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'enrollment_closes_at' => now()->addDays(7),
            'status' => 'running',
        ]);
        $this->campagne->options()->create(['label' => '5 courses', 'threshold' => 5, 'reward' => 1000, 'position' => 1]);

        $idAgent = $this->agentAvecCoursesReelles();

        $reponse = $this->getJson("/api/v1.0/getGoalCampaigns?id_agent={$idAgent}");

        $reponse->assertOk()->assertJsonPath('response', 200);

        $donnees = collect($reponse->json('data'));
        $trouvee = $donnees->firstWhere('id', $this->campagne->id);

        $this->assertNotNull($trouvee, 'La campagne publiée doit apparaître dans getGoalCampaigns.');
        $this->assertCount(1, $trouvee['options']);
        $this->assertSame('5 courses', $trouvee['options'][0]['label']);
        $this->assertNull($trouvee['enrollment'], 'Sans engagement, la clé enrollment doit être nulle.');
    }

    /**
     * La progression compte les vraies courses Success de la fenêtre, pas un
     * chiffre inventé — c'est le cœur du modèle tout-ou-rien du document de
     * conception.
     */
    public function test_la_progression_correspond_au_compte_reel_des_courses(): void
    {
        $idAgent = $this->agentAvecCoursesReelles();

        // Fenêtre large : on compare le calcul à une requête manuelle sur la
        // même fenêtre, peu importe le nombre exact de courses qu'elle contient.
        $debut = Carbon::now()->subYears(3);
        $fin = Carbon::now();

        $this->campagne = GoalCampaign::create([
            'title' => 'Test — progression réelle',
            'metric' => 'rides',
            'ride_kind' => 'delivery',
            'starts_at' => $debut,
            'ends_at' => $fin,
            'enrollment_closes_at' => $fin,
            'status' => 'running',
        ]);
        $option = $this->campagne->options()->create(['label' => '1 course', 'threshold' => 1, 'reward' => 100, 'position' => 1]);

        $compteAttendu = order_detail::where('id_agent', $idAgent)
            ->where('status', 'Success')
            ->where('delivery_type', '!=', 'coursier')
            ->whereBetween('created_at', [$debut, $fin])
            ->count();

        $resultat = ObjectifProgression::calculer($this->campagne, $idAgent, $option->id);

        $this->assertSame($compteAttendu, $resultat['progress']);
        $this->assertSame($compteAttendu >= 1, $resultat['achieved']);
    }

    public function test_enrollGoalCampaign_refuse_apres_la_fermeture_de_l_inscription(): void
    {
        $this->campagne = GoalCampaign::create([
            'title' => 'Test — inscription fermée',
            'metric' => 'rides',
            'ride_kind' => null,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDay(),
            // Déjà fermée : c'est ce que le test vérifie.
            'enrollment_closes_at' => now()->subHour(),
            'status' => 'running',
        ]);
        $option = $this->campagne->options()->create(['label' => '5 courses', 'threshold' => 5, 'reward' => 1000, 'position' => 1]);

        $idAgent = $this->agentAvecCoursesReelles();

        $reponse = $this->postJson('/api/v1.0/enrollGoalCampaign', [
            'id_agent' => $idAgent,
            'id_campaign' => $this->campagne->id,
            'id_option' => $option->id,
        ]);

        $reponse->assertStatus(409);
    }

    public function test_enrollGoalCampaign_puis_verrouillage_apres_une_course_comptee(): void
    {
        $idAgent = $this->agentAvecCoursesReelles();

        // Fenêtre couvrant la course réelle trouvée, pour que l'engagement se
        // verrouille dès le premier appel à getGoalCampaigns.
        $this->campagne = GoalCampaign::create([
            'title' => 'Test — verrouillage',
            'metric' => 'rides',
            'ride_kind' => null,
            'starts_at' => now()->subYears(3),
            'ends_at' => now()->addDay(),
            'enrollment_closes_at' => now()->addDay(),
            'status' => 'running',
        ]);
        $option = $this->campagne->options()->create(['label' => '1 course', 'threshold' => 1, 'reward' => 100, 'position' => 1]);

        $this->postJson('/api/v1.0/enrollGoalCampaign', [
            'id_agent' => $idAgent,
            'id_campaign' => $this->campagne->id,
            'id_option' => $option->id,
        ])->assertOk();

        // Ce recalcul verrouille l'engagement dès qu'au moins une course est comptée.
        $this->getJson("/api/v1.0/getGoalCampaigns?id_agent={$idAgent}")->assertOk();

        $enrollment = $this->campagne->enrollments()->where('agent_id', $idAgent)->first();
        $this->assertNotNull($enrollment->locked_at, "L'engagement doit se verrouiller dès la première course comptée.");

        // Changer d'option n'est alors plus permis.
        $autreOption = $this->campagne->options()->create(['label' => '2 courses', 'threshold' => 2, 'reward' => 200, 'position' => 2]);
        $this->postJson('/api/v1.0/enrollGoalCampaign', [
            'id_agent' => $idAgent,
            'id_campaign' => $this->campagne->id,
            'id_option' => $autreOption->id,
        ])->assertStatus(409);
    }
}
