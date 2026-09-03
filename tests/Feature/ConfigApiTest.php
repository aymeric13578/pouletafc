<?php

namespace Tests\Feature;

use App\Support\PointDeLivraison;
use Tests\TestCase;

/** /api/v2/config : tout ce qu'une application lit au démarrage. Sans base (config() + doublure). */
class ConfigApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mobile_app.android.version_code', 40);
        config()->set('mobile_app.android.min_version_code', 38);
        config()->set('mobile_app.android.version', '1.0.5');
        config()->set('mobile_app.agent.version_code', 7);
        config()->set('mobile_app.agent.min_version_code', 3);
        config()->set('mobile_app.contact.telephone', '697000000');
        config()->set('mobile_app.contact.whatsapp', null);
        config()->set('mobile_app.fonctionnalites.coursier', false);

        $this->app->instance(PointDeLivraison::class, new class extends PointDeLivraison {
            public function pointDeRetrait(): ?array
            {
                return ['id' => 12, 'nom' => 'Marché — Centre', 'lat' => 9.2981, 'lon' => 13.399];
            }
        });
    }

    public function test_la_configuration_cliente_est_complete(): void
    {
        $this->getJson('/api/v2/config')
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.app', 'client')
            ->assertJsonPath('data.version.code', 40)
            ->assertJsonPath('data.version.min_code', 38)
            ->assertJsonPath('data.version.nom', '1.0.5')
            ->assertJsonPath('data.point_de_retrait.id', 12)
            ->assertJsonPath('data.point_de_retrait.lat', 9.2981)
            ->assertJsonPath('data.contact.telephone', '697000000')
            ->assertJsonPath('data.contact.whatsapp', null)
            ->assertJsonPath('data.fonctionnalites.coursier', false)
            ->assertJsonPath('data.fonctionnalites.vip', true)
            ->assertJsonStructure(['data' => ['genere_a', 'version' => ['download_url']]]);
    }

    public function test_la_configuration_agent_porte_sa_propre_version(): void
    {
        $this->postJson('/api/v2/config', ['app' => 'agent'])
            ->assertOk()
            ->assertJsonPath('data.app', 'agent')
            ->assertJsonPath('data.version.code', 7)
            ->assertJsonPath('data.version.min_code', 3);
    }

    public function test_une_app_inconnue_est_refusee(): void
    {
        $this->getJson('/api/v2/config?app=web')->assertStatus(422);
    }

    /**
     * Les autres cas remplacent PointDeLivraison par une doublure, si bien que
     * pointDeRetrait() n'y est jamais exécuté : une classe mal référencée dans
     * cette méthode passait les tests et cassait la production (erreur 500 sur
     * /api/v2/config, 2026-09-03). Ce cas l'appelle vraiment.
     */
    public function test_le_point_de_retrait_reel_se_resout_sans_erreur_de_classe(): void
    {
        $this->app->forgetInstance(PointDeLivraison::class);

        try {
            $point = app(PointDeLivraison::class)->pointDeRetrait();
        } catch (\Illuminate\Database\QueryException $e) {
            // Base locale en retard de migrations : l'absence de table n'est
            // pas ce qu'on éprouve ici.
            $this->markTestSkipped('Tables indisponibles : ' . $e->getMessage());
        }

        $this->assertTrue($point === null || is_array($point));

        if (is_array($point)) {
            $this->assertSame(['id', 'nom', 'lat', 'lon'], array_keys($point));
        }
    }

    public function test_sans_point_de_retrait_configure_le_champ_vaut_null(): void
    {
        $this->app->instance(PointDeLivraison::class, new class extends PointDeLivraison {
            public function pointDeRetrait(): ?array { return null; }
        });

        $this->getJson('/api/v2/config')->assertJsonPath('data.point_de_retrait', null);
    }
}
