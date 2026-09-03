<?php

namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Quel prix enregistrer à la création d'une course : celui que le client a
 * envoyé, ou celui que le serveur calcule ? Testé comme fonction pure parce
 * que la table `clando` n'existe pas sur la base locale (51 migrations en
 * attente) — Insertclando lui-même n'est donc pas testable ici.
 */
class PrixRetenuTest extends TestCase
{
    private function moteur(): Tarification
    {
        $parametres = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50]);

        return new Tarification(new class($parametres) extends GrilleTarifaire {
            public function __construct(private Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return null; }
            public function parametres(): ?Parameter { return $this->q; }
        });
    }

    public function test_avec_une_distance_le_prix_serveur_l_emporte_sur_le_prix_client(): void
    {
        Log::shouldReceive('warning')->once();

        // Le client annonce 100 F pour 4,2 km : le serveur retient 1050.
        $this->assertSame(1050, $this->moteur()->prixRetenu(Tarif::CLANDO, '100', '4.2'));
    }

    public function test_un_prix_client_egal_au_prix_serveur_ne_journalise_rien(): void
    {
        Log::shouldReceive('warning')->never();

        $this->assertSame(1050, $this->moteur()->prixRetenu(Tarif::CLANDO, 1050, 4.2));
    }

    public function test_sans_distance_le_prix_client_est_conserve_comme_avant(): void
    {
        Log::shouldReceive('warning')->never();

        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, '800', null));
        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, 800, 0));
        $this->assertSame(800, $this->moteur()->prixRetenu(Tarif::CLANDO, 800, 'abc'));
    }

    public function test_sans_distance_un_prix_client_invalide_donne_null(): void
    {
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, '0', null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, 'abc', null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, null, null));
        $this->assertNull($this->moteur()->prixRetenu(Tarif::CLANDO, -5, null));
    }

    public function test_avec_une_distance_un_prix_client_absent_n_empeche_rien(): void
    {
        Log::shouldReceive('warning')->never();

        // Un futur client n'enverra plus de prix du tout.
        $this->assertSame(1600, $this->moteur()->prixRetenu(Tarif::CLANDO, null, 4.2, vip: true));
    }
}
