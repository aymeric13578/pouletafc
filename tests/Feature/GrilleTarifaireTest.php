<?php

namespace Tests\Feature;

use App\Models\Parameter;
use App\Models\Tarif;
use App\Support\GrilleTarifaire;
use Tests\TestCase;

/**
 * Le passage de l'ancienne tarification plate aux grilles par service.
 *
 * Ce qui compte ici n'est pas seulement que les grilles s'appliquent, mais
 * que **rien ne change tant qu'aucune grille n'existe** : ces montants sont
 * versés aux agents, et créer les tables ne doit pas déplacer un franc.
 *
 * Volontairement SANS RefreshDatabase : ce test tourne sur la base de
 * développement, qui contient de vraies données. Ce qu'il crée, il le
 * supprime.
 */
class GrilleTarifaireTest extends TestCase
{
    private array $creees = [];

    /** Ligne `parameters` créée par le test faute d'en trouver une, à retirer ensuite. */
    private ?Parameter $parametresTemporaires = null;

    protected function tearDown(): void
    {
        foreach ($this->creees as $tarif) {
            $tarif->delete();
        }

        $this->parametresTemporaires?->delete();

        parent::tearDown();
    }

    /**
     * La grille plate en vigueur, créée au besoin.
     *
     * Les deux tests de non-régression ci-dessous sont les plus importants du
     * fichier : ils vérifient qu'aucun montant ne bouge tant qu'aucune grille
     * par service n'existe. Les laisser sauter faute de ligne `parameters` en
     * base reviendrait à ne pas vérifier cela du tout.
     */
    private function parametresEnVigueur(): Parameter
    {
        $actif = Parameter::active();

        if ($actif) {
            return $actif;
        }

        // Volontairement réduit aux colonnes dont dépend le calcul vérifié :
        // les bases de développement de cet écosystème n'ont pas toutes les
        // mêmes colonnes que la production, et lister les autres ferait
        // échouer le test pour une raison étrangère à ce qu'il vérifie.
        return $this->parametresTemporaires = Parameter::create([
            'clando_kilometer' => 91,
            'command_kilometer' => 100,
            'min_price_clando' => 200,
            'min_price_command' => 400,
            'clando_agent_commission' => 20,
            'clando_agent_command' => 20,
            'vip_percentage' => 25,
            'status' => Parameter::ACTIF,
        ]);
    }

    private function grille(string $service, array $plage): Tarif
    {
        $tarif = Tarif::create([
            'service' => $service,
            'libelle' => 'Grille de vérification',
            'status' => Tarif::ACTIF,
        ]);

        // Plage couvrant la journée entière : le test ne doit pas dépendre de
        // l'heure à laquelle il est lancé.
        $tarif->plages()->create($plage + [
            'debut' => '00:00', 'fin' => '00:00',
            'prix_min' => 0, 'prix_max' => null, 'prix_km' => 100,
            'ordre' => 0,
        ]);

        $this->creees[] = $tarif;

        return $tarif;
    }

    public function test_sans_grille_la_commission_clando_reste_celle_d_avant(): void
    {
        $parametres = $this->parametresEnVigueur();

        $attendu = round(1000 * (float) $parametres->clando_agent_commission / 100);

        $this->assertSame($attendu, (new GrilleTarifaire())->commissionClando(1000));
    }

    public function test_sans_grille_la_commission_livraison_reste_celle_d_avant(): void
    {
        $parametres = $this->parametresEnVigueur();

        // L'ancien calcul portait sur le total des articles, pas sur les frais
        // de livraison : c'est exactement ce que le repli doit reproduire.
        $attendu = round(20000 * (float) $parametres->clando_agent_commission / 100);

        $this->assertSame(
            $attendu,
            (new GrilleTarifaire())->commissionLivraison(fraisDeLivraison: 1500, totalArticles: 20000)
        );
    }

    public function test_avec_grille_la_commission_livraison_porte_sur_les_seuls_frais(): void
    {
        $this->grille(Tarif::LIVRAISON, ['commission' => 10]);

        // 10 % de 1 500 F de portage = 150 F. Le panier de 20 000 F n'entre
        // pas dans le calcul : c'est toute la correction.
        $this->assertSame(
            150.0,
            (new GrilleTarifaire())->commissionLivraison(fraisDeLivraison: 1500, totalArticles: 20000)
        );
    }

    public function test_avec_grille_le_clando_distingue_classique_et_vip(): void
    {
        $this->grille(Tarif::CLANDO, ['commission' => 20, 'commission_vip' => 30]);

        $grille = new GrilleTarifaire();

        $this->assertSame(200.0, $grille->commissionClando(1000));
        $this->assertSame(300.0, $grille->commissionClando(1000, vip: true));
    }

    public function test_les_tarifs_exposes_a_l_application_suivent_la_grille(): void
    {
        $this->grille(Tarif::COURSIER, [
            'prix_km' => 200, 'prix_min' => 500, 'prix_max' => 5000, 'commission' => 15,
        ]);

        $grille = new GrilleTarifaire();

        $coursier = $grille->pourApplication(Tarif::COURSIER);

        $this->assertNotNull($coursier);
        $this->assertSame(200, $coursier['prix_km']);
        $this->assertSame(500, $coursier['prix_min']);
        $this->assertSame(5000, $coursier['prix_max']);

        // Un service sans grille vaut null : l'application garde alors les
        // champs plats de `parameters` qu'elle lit déjà.
        $this->assertNull($grille->pourApplication('service_inexistant'));
    }
}
