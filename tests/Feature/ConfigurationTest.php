<?php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Écran de configuration : grille tarifaire et pourcentages de commission.
 *
 * Une seule ligne s'applique — celle au statut 'Success'. Tout le code appelant
 * fait `Parameter::where('status', 'Success')->first()` : avec deux lignes
 * actives, la grille réellement appliquée devient imprévisible ; avec zéro,
 * toutes les commissions retombent silencieusement à 0.
 *
 * Les cas qui écrivent sont enveloppés par DatabaseTransactions pour ne pas
 * toucher la grille en vigueur de la base de développement.
 */
class ConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function valeurs(array $remplacements = []): array
    {
        return $remplacements + [
            'clando_kilometer' => 100,
            'command_kilometer' => 120,
            'min_price_clando' => 200,
            'min_price_command' => 500,
            'clando_agent_commission' => 20,
            'clando_agent_command' => 25,
            'delivery_agent_commission' => 15,
            'vip_percentage' => 30,
        ];
    }

    private function creerInactive(array $remplacements = []): Parameter
    {
        return Parameter::create($this->valeurs($remplacements) + ['status' => Parameter::INACTIF]);
    }

    public function test_l_ecran_s_affiche(): void
    {
        $this->actingAs($this->admin())->get('/dashboard/configuration')->assertOk();
    }

    public function test_il_est_ferme_aux_clients(): void
    {
        $client = User::where('role', 'user')->firstOrFail();

        $this->actingAs($client)->get('/dashboard/configuration')->assertForbidden();
    }

    public function test_le_lien_est_dans_la_navigation(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertSee('Configuration');
    }

    public function test_l_ecran_montre_les_pourcentages_appliques(): void
    {
        $active = Parameter::active();

        if (! $active) {
            $this->markTestSkipped('Aucune configuration active dans cette base.');
        }

        $this->actingAs($this->admin())
            ->get('/dashboard/configuration')
            ->assertOk()
            ->assertSee($active->clando_agent_commission . ' %');
    }

    public function test_activer_une_configuration_desactive_toutes_les_autres(): void
    {
        $premiere = $this->creerInactive();
        $seconde = $this->creerInactive();

        $premiere->activer();
        $seconde->activer();

        $this->assertSame(
            1,
            Parameter::where('status', Parameter::ACTIF)->count(),
            'Une seule configuration doit rester active.'
        );

        $this->assertSame(Parameter::ACTIF, $seconde->fresh()->status);
        $this->assertSame(Parameter::INACTIF, $premiere->fresh()->status);
    }

    public function test_activer_desactive_aussi_la_grille_deja_en_vigueur(): void
    {
        $ancienne = $this->creerInactive();
        $ancienne->activer();

        $nouvelle = $this->creerInactive();
        $nouvelle->activer();

        $this->assertSame(Parameter::INACTIF, $ancienne->fresh()->status);
        $this->assertSame($nouvelle->id, Parameter::active()->id);
    }

    public function test_la_configuration_active_ne_peut_pas_etre_supprimee(): void
    {
        $active = $this->creerInactive();
        $active->activer();

        $this->assertFalse(
            $active->estSupprimable(),
            'Supprimer la grille active laisserait l\'application sans tarifs.'
        );
    }

    public function test_une_configuration_inactive_peut_etre_supprimee(): void
    {
        $this->assertTrue($this->creerInactive()->estSupprimable());
    }

    public function test_un_pourcentage_ne_depasse_pas_cent(): void
    {
        // La valeur est appliquée telle quelle (prix × valeur / 100) : au-delà
        // de 100, la commission dépasserait le montant de la course.
        $validation = Validator::make(
            $this->valeurs(['clando_agent_commission' => 150]),
            Parameter::regles(),
            Parameter::messagesValidation()
        );

        $this->assertTrue($validation->fails());
        $this->assertArrayHasKey('clando_agent_commission', $validation->errors()->toArray());
    }

    public function test_les_valeurs_negatives_sont_refusees(): void
    {
        $validation = Validator::make(
            $this->valeurs(['min_price_clando' => -50]),
            Parameter::regles(),
            Parameter::messagesValidation()
        );

        $this->assertTrue($validation->fails());
        $this->assertArrayHasKey('min_price_clando', $validation->errors()->toArray());
    }

    public function test_une_grille_complete_est_acceptee(): void
    {
        $validation = Validator::make($this->valeurs(), Parameter::regles(), Parameter::messagesValidation());

        $this->assertFalse($validation->fails(), 'Une grille valide ne doit produire aucune erreur.');
    }

    public function test_la_commission_livreur_est_enregistrable(): void
    {
        // La colonne existait en base mais était absente de $fillable : aucun
        // écran ne pouvait l'écrire, elle restait bloquée à 0.
        $configuration = $this->creerInactive(['delivery_agent_commission' => 18]);

        $this->assertSame(18, (int) $configuration->fresh()->delivery_agent_commission);
    }
}
