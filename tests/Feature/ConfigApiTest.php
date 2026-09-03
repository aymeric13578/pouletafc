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

    public function test_sans_point_de_retrait_configure_le_champ_vaut_null(): void
    {
        $this->app->instance(PointDeLivraison::class, new class extends PointDeLivraison {
            public function pointDeRetrait(): ?array { return null; }
        });

        $this->getJson('/api/v2/config')->assertJsonPath('data.point_de_retrait', null);
    }
}
