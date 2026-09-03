<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * getAppVersion : ce que les deux applications lisent à l'ouverture pour
 * savoir si une mise à jour est disponible (version_code) et, depuis la
 * tranche « server-driven », si leur build est encore accepté
 * (min_version_code — 0 = aucune exigence). Aucune base de données : tout
 * vient de config/mobile_app.php.
 */
class AppVersionTest extends TestCase
{
    public function test_l_application_agent_recoit_sa_version_et_son_minimum(): void
    {
        config()->set('mobile_app.agent.version_code', 7);
        config()->set('mobile_app.agent.version', '1.0.5');
        config()->set('mobile_app.agent.min_version_code', 3);

        $this->getJson('/api/v1.0/getAppVersion?app=agent')
            ->assertOk()
            ->assertJsonPath('response', 200)
            ->assertJsonPath('data.version_code', 7)
            ->assertJsonPath('data.version_name', '1.0.5')
            ->assertJsonPath('data.min_version_code', 3);
    }

    public function test_l_application_cliente_recoit_sa_version_et_son_minimum(): void
    {
        config()->set('mobile_app.android.version_code', 40);
        config()->set('mobile_app.android.min_version_code', 38);

        $this->postJson('/api/v1.0/getAppVersion')
            ->assertOk()
            ->assertJsonPath('data.version_code', 40)
            ->assertJsonPath('data.min_version_code', 38);
    }

    public function test_sans_reglage_le_minimum_vaut_zero_et_n_exclut_personne(): void
    {
        config()->set('mobile_app.agent.min_version_code', 0);
        config()->set('mobile_app.android.min_version_code', 0);

        $this->getJson('/api/v1.0/getAppVersion?app=agent')->assertJsonPath('data.min_version_code', 0);
        $this->getJson('/api/v1.0/getAppVersion')->assertJsonPath('data.min_version_code', 0);
    }
}
