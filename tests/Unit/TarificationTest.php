<?php

namespace Tests\Unit;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;
use App\Support\Devis;
use App\Support\GrilleTarifaire;
use App\Support\Tarification;
use Tests\TestCase;

/**
 * Le moteur est testé sans base : les grilles et paramètres sont des modèles
 * non persistés injectés via une doublure de GrilleTarifaire. La base locale
 * a 51 migrations en attente (pas de table `tarifs`), et surtout ces
 * formules doivent être vérifiables sans dépendre de la ligne `parameters`
 * qui traîne en base.
 */
class TarificationTest extends TestCase
{
    /** Doublure : renvoie la plage et les paramètres donnés, sans requête. */
    private function grille(?TarifPlage $plage, ?Parameter $parametres): GrilleTarifaire
    {
        return new class($plage, $parametres) extends GrilleTarifaire {
            public function __construct(private ?TarifPlage $p, private ?Parameter $q) {}
            public function plage(string $service): ?TarifPlage { return $this->p; }
            public function parametres(): ?Parameter { return $this->q; }
        };
    }

    private function parametres(array $valeurs = []): Parameter
    {
        return new Parameter($valeurs + [
            'clando_kilometer' => 250, 'min_price_clando' => 500, 'vip_percentage' => 50,
            'command_kilometer' => 63, 'min_price_command' => 400,
            'coursier_kilometer' => 200, 'min_price_coursier' => 500,
        ]);
    }

    // --- Repli sur `parameters` : copie exacte des formules de l'app cliente ---

    public function test_clando_sans_grille_applique_le_plancher_et_l_arrondi_au_50(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 1 km × 250 = 250 < 500 → plancher 500
        $this->assertSame(500, $moteur->devis(Tarif::CLANDO, 1.0)->prix);
        // 4.2 km × 250 = 1050 → déjà multiple de 50
        $this->assertSame(1050, $moteur->devis(Tarif::CLANDO, 4.2)->prix);
        // 4.21 km × 250 = 1052,5 → 1100
        $this->assertSame(1100, $moteur->devis(Tarif::CLANDO, 4.21)->prix);
    }

    public function test_clando_vip_sans_grille_majore_le_prix_classique_deja_arrondi(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        $devis = $moteur->devis(Tarif::CLANDO, 4.2, vip: true);

        // classique 1050 ; vip = ceil50(1050 + 1050 × 50 %) = ceil50(1575) = 1600
        $this->assertSame(1050, $devis->prixClassique);
        $this->assertSame(1600, $devis->prix);
        $this->assertTrue($devis->vip);
        $this->assertSame('parameters', $devis->source);
    }

    public function test_livraison_sans_grille_reproduit_le_calcul_du_panier(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 3 km × 63 = 189 < 400 → 400 ; 10 km × 63 = 630 → 650
        $this->assertSame(400, $moteur->devis(Tarif::LIVRAISON, 3.0)->prix);
        $this->assertSame(650, $moteur->devis(Tarif::LIVRAISON, 10.0)->prix);
    }

    public function test_coursier_sans_grille_est_additif_pas_un_plancher(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        // 500 + 3 km × 200 = 1100 (et non max(600, 500))
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
        // 500 + 2.3 × 200 = 960 → 1000
        $this->assertSame(1000, $moteur->devis(Tarif::COURSIER, 2.3)->prix);
    }

    public function test_le_vip_est_ignore_hors_clando(): void
    {
        $moteur = new Tarification($this->grille(null, $this->parametres()));

        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0, vip: true)->prix);
        $this->assertFalse($moteur->devis(Tarif::COURSIER, 3.0, vip: true)->vip);
    }

    public function test_sans_parameters_les_valeurs_historiques_du_client_s_appliquent(): void
    {
        $moteur = new Tarification($this->grille(null, null));

        $devis = $moteur->devis(Tarif::CLANDO, 4.2);

        $this->assertSame(1050, $devis->prix);
        $this->assertSame('defaut', $devis->source);
        $this->assertSame(650, $moteur->devis(Tarif::LIVRAISON, 10.0)->prix);
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
    }

    public function test_une_ligne_parameters_sans_tarif_coursier_retombe_sur_l_historique(): void
    {
        $sansCoursier = new Parameter(['clando_kilometer' => 250, 'min_price_clando' => 500]);
        $moteur = new Tarification($this->grille(null, $sansCoursier));

        // coursier_kilometer / min_price_coursier absents → 500 + 200/km
        $this->assertSame(1100, $moteur->devis(Tarif::COURSIER, 3.0)->prix);
    }

    // --- Avec grille : TarifPlage décide ---

    public function test_avec_grille_c_est_la_plage_qui_calcule(): void
    {
        $plage = new TarifPlage([
            'debut' => '00:00', 'fin' => '00:00',
            'prix_km' => 300, 'prix_min' => 700, 'prix_max' => 5000,
            'commission' => 20, 'majoration_vip' => 25, 'ordre' => 0,
        ]);
        $moteur = new Tarification($this->grille($plage, $this->parametres()));

        $devis = $moteur->devis(Tarif::CLANDO, 4.0, vip: true);

        // 4 × 300 = 1200 ; +25 % = 1500 ; classique 1200
        $this->assertSame(1500, $devis->prix);
        $this->assertSame(1200, $devis->prixClassique);
        $this->assertSame('grille', $devis->source);
        $this->assertSame(300, $devis->tarif['prix_km']);
        $this->assertSame('00:00', $devis->tarif['debut']);
        // 30 km × 300 = 9000 → plafond 5000
        $this->assertSame(5000, $moteur->devis(Tarif::CLANDO, 30.0)->prix);
    }

    public function test_un_service_inconnu_est_refuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Tarification($this->grille(null, null)))->devis('colis', 1.0);
    }

    public function test_to_array_expose_la_forme_attendue_par_les_applications(): void
    {
        $devis = (new Tarification($this->grille(null, $this->parametres())))->devis(Tarif::CLANDO, 4.2, vip: true);

        $tableau = $devis->toArray();

        $this->assertSame(
            ['service', 'distance_km', 'type', 'prix', 'prix_classique', 'devise', 'source', 'tarif', 'calcule_a'],
            array_keys($tableau)
        );
        $this->assertSame('vip', $tableau['type']);
        $this->assertSame('XAF', $tableau['devise']);
        $this->assertInstanceOf(Devis::class, $devis);
    }
}
