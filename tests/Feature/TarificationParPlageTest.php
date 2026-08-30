<?php

namespace Tests\Feature;

use App\Models\Tarif;
use App\Support\MajorationBoutique;
use Tests\TestCase;

/**
 * Le calcul d'un prix par plage horaire, et la majoration de boutique.
 *
 * Ces deux règles décident de ce que paie un client : elles méritent d'être
 * vérifiées ailleurs que de visu sur un écran. Le cas qui compte le plus est
 * la plage de nuit — sa fin précède son début, et une comparaison naïve ne
 * l'aurait jamais fait correspondre, laissant la majoration nocturne sans
 * effet sans que rien ne le signale.
 *
 * Volontairement SANS RefreshDatabase : ce test tourne sur la base de
 * développement, qui contient de vraies données de travail. Ce qu'il crée, il
 * le supprime.
 */
class TarificationParPlageTest extends TestCase
{
    private ?Tarif $tarif = null;

    protected function tearDown(): void
    {
        $this->tarif?->delete();

        parent::tearDown();
    }

    private function plage(array $attributs)
    {
        $this->tarif ??= Tarif::create([
            'service' => Tarif::COURSIER,
            'libelle' => 'Grille de vérification',
            'status' => Tarif::INACTIF,
        ]);

        return $this->tarif->plages()->create($attributs + [
            'prix_min' => 500,
            'prix_max' => null,
            'prix_km' => 200,
            'commission' => 10,
            'ordre' => 0,
        ]);
    }

    public function test_une_plage_de_nuit_couvre_bien_minuit(): void
    {
        $nuit = $this->plage(['debut' => '18:00', 'fin' => '06:00']);

        $this->assertTrue($nuit->couvre('23:30'), '23h30 est dans la plage de nuit');
        $this->assertTrue($nuit->couvre('00:10'), 'Dix minutes après minuit aussi');
        $this->assertTrue($nuit->couvre('02:00'), '2h du matin aussi');
        $this->assertFalse($nuit->couvre('12:00'), 'Midi n\'y est pas');
        $this->assertFalse($nuit->couvre('17:59'), 'Une minute avant le début non plus');
    }

    public function test_une_plage_de_jour_ne_couvre_que_ses_heures(): void
    {
        $jour = $this->plage(['debut' => '06:00', 'fin' => '18:00', 'ordre' => 1]);

        $this->assertTrue($jour->couvre('06:00'), 'La borne de début est incluse');
        $this->assertTrue($jour->couvre('12:00'));
        $this->assertFalse($jour->couvre('18:00'), 'La borne de fin appartient à la plage suivante');
        $this->assertFalse($jour->couvre('03:00'));
    }

    public function test_le_plancher_et_le_plafond_bornent_le_prix(): void
    {
        $plage = $this->plage([
            'debut' => '06:00', 'fin' => '18:00',
            'prix_min' => 500, 'prix_max' => 1500, 'prix_km' => 200,
        ]);

        $this->assertSame(500, $plage->prixPour(0.5), 'Une course très courte est facturée au plancher');
        $this->assertSame(1000, $plage->prixPour(5), '200 F/km sur 5 km');
        $this->assertSame(1500, $plage->prixPour(40), 'Le plafond borne une longue course');
    }

    public function test_le_prix_est_arrondi_au_multiple_de_cinquante(): void
    {
        $plage = $this->plage([
            'debut' => '06:00', 'fin' => '18:00',
            'prix_min' => 0, 'prix_km' => 91,
        ]);

        // 91 × 3 = 273 → 300 ; 91 × 10 = 910 → 950.
        $this->assertSame(300, $plage->prixPour(3));
        $this->assertSame(950, $plage->prixPour(10));
    }

    public function test_la_majoration_vip_precede_le_plafond(): void
    {
        $plage = $this->plage([
            'debut' => '06:00', 'fin' => '18:00',
            'prix_min' => 0, 'prix_max' => 2000, 'prix_km' => 200,
            'majoration_vip' => 50,
        ]);

        $this->assertSame(1000, $plage->prixPour(5), 'Course classique');
        $this->assertSame(1500, $plage->prixPour(5, vip: true), 'Majorée de 50 %');
        $this->assertSame(2000, $plage->prixPour(20, vip: true), 'Puis bornée par le plafond');
    }

    public function test_la_commission_vip_prend_le_pas_quand_elle_existe(): void
    {
        $plage = $this->plage([
            'debut' => '06:00', 'fin' => '18:00',
            'commission' => 20, 'commission_vip' => 30,
        ]);

        $this->assertSame(200.0, $plage->commissionPour(1000));
        $this->assertSame(300.0, $plage->commissionPour(1000, vip: true));

        // Sans taux VIP, une course VIP retombe sur le taux classique plutôt
        // que sur zéro — l'entreprise ne doit pas perdre sa commission parce
        // qu'un champ facultatif est resté vide.
        $sansVip = $this->plage([
            'debut' => '18:00', 'fin' => '06:00',
            'commission' => 20, 'commission_vip' => null, 'ordre' => 2,
        ]);
        $this->assertSame(200.0, $sansVip->commissionPour(1000, vip: true));
    }

    public function test_la_majoration_de_boutique_arrondit_au_multiple_de_cinquante(): void
    {
        // 1200 majorés de 5 % = 1260, arrondi à 1300.
        $this->assertSame(1300, MajorationBoutique::arrondi(1200 * 1.05));
        // 1000 majorés de 5 % = 1050, déjà un multiple de 50.
        $this->assertSame(1050, MajorationBoutique::arrondi(1000 * 1.05));
        $this->assertSame(0, MajorationBoutique::arrondi(0));
    }

    public function test_une_boutique_sans_facturation_ne_majore_rien(): void
    {
        $majoration = new MajorationBoutique();

        // Identifiants inexistants : aucune facturation ne peut s'y rattacher.
        $this->assertSame(1000, $majoration->prixAffiche(1000, 999999, 999999));
        $this->assertNull($majoration->taux(999999, 999999));
        // Un produit hors boutique connue ne doit pas non plus être majoré.
        $this->assertSame(1000, $majoration->prixAffiche(1000, null, null));
    }
}
