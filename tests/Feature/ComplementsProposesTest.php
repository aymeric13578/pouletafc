<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ComplementsProposes;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Règle de proposition des compléments.
 *
 * Un complément est un produit, marqué comme tel et rattaché aux plats qui le
 * proposent. La règle tient en deux phrases mais se trompe facilement : dès
 * qu'un seul produit du panier en propose, on demande ; et la liste offerte est
 * l'union sans doublon des compléments de tous les produits.
 */
class ComplementsProposesTest extends TestCase
{
    private array $crees = [];

    private function produit(string $nom, bool $complement = false, string $statut = 'Success'): Product
    {
        $produit = Product::create([
            'name' => $nom,
            'price' => 1000,
            'stock_init' => 10,
            'status' => $statut,
            'is_complement' => $complement,
        ]);

        $this->crees[] = $produit->id;

        return $produit;
    }

    protected function tearDown(): void
    {
        DB::table('product_complement')
            ->whereIn('product_id', $this->crees)
            ->orWhereIn('complement_id', $this->crees)
            ->delete();

        Product::whereIn('id', $this->crees)->forceDelete();

        parent::tearDown();
    }

    private function regle(): ComplementsProposes
    {
        return new ComplementsProposes();
    }

    public function test_un_produit_sans_complement_ne_declenche_aucune_question(): void
    {
        $plat = $this->produit('Plat nu');

        $this->assertFalse($this->regle()->fautIlDemander([$plat->id]));
        $this->assertTrue($this->regle()->pourProduits([$plat->id])->isEmpty());
    }

    public function test_un_seul_produit_accompagne_suffit_a_declencher_la_question(): void
    {
        $poulet = $this->produit('Poulet');
        $nu = $this->produit('Boisson nue');
        $frites = $this->produit('Frites', complement: true);

        $poulet->complements()->attach($frites->id);

        // Se taire priverait le client de l'accompagnement du poulet, sous
        // prétexte que la boisson n'en a pas.
        $this->assertTrue($this->regle()->fautIlDemander([$poulet->id, $nu->id]));
        $this->assertFalse($this->regle()->tousEnProposent([$poulet->id, $nu->id]));
    }

    /*
     | Deux plats accompagnés des mêmes frites ne doivent pas les faire
     | apparaître deux fois dans la liste.
     */
    public function test_la_liste_proposee_est_unique(): void
    {
        $poulet = $this->produit('Poulet');
        $poisson = $this->produit('Poisson');
        $frites = $this->produit('Frites', complement: true);
        $salade = $this->produit('Salade', complement: true);

        $poulet->complements()->attach([$frites->id, $salade->id]);
        $poisson->complements()->attach([$frites->id]);

        $liste = $this->regle()->pourProduits([$poulet->id, $poisson->id]);

        $this->assertCount(2, $liste);
        $this->assertEqualsCanonicalizing(
            [$frites->id, $salade->id],
            $liste->pluck('id')->all()
        );
        $this->assertTrue($this->regle()->tousEnProposent([$poulet->id, $poisson->id]));
    }

    /** Un complément retiré de la vente ne doit plus être proposé. */
    public function test_un_complement_desactive_disparait_de_la_liste(): void
    {
        $poulet = $this->produit('Poulet');
        $frites = $this->produit('Frites', complement: true, statut: 'pending');

        $poulet->complements()->attach($frites->id);

        $this->assertTrue($this->regle()->pourProduits([$poulet->id])->isEmpty());
        $this->assertFalse($this->regle()->fautIlDemander([$poulet->id]));
    }

    /*
     | Un identifiant inconnu ne doit pas passer pour un produit accompagné.
     |
     | Sans ce contrôle, un panier contenant un produit supprimé serait annoncé
     | comme entièrement accompagné.
     */
    public function test_un_produit_introuvable_n_est_pas_repute_accompagne(): void
    {
        $poulet = $this->produit('Poulet');
        $frites = $this->produit('Frites', complement: true);
        $poulet->complements()->attach($frites->id);

        $this->assertFalse($this->regle()->tousEnProposent([$poulet->id, 99999999]));
    }

    public function test_la_charge_pour_l_application_porte_tout_le_necessaire(): void
    {
        $poulet = $this->produit('Poulet');
        $frites = $this->produit('Frites', complement: true);
        $poulet->complements()->attach($frites->id);

        $charge = $this->regle()->charge([$poulet->id]);

        $this->assertTrue($charge['demander']);
        $this->assertTrue($charge['tous_en_proposent']);
        $this->assertCount(1, $charge['complements']);
        $this->assertSame('Frites', $charge['complements'][0]['name']);
        $this->assertSame(1000, $charge['complements'][0]['price']);
    }

    public function test_un_panier_vide_ne_propose_rien(): void
    {
        $this->assertFalse($this->regle()->fautIlDemander([]));
        $this->assertFalse($this->regle()->tousEnProposent([]));
        $this->assertTrue($this->regle()->pourProduits([])->isEmpty());
    }
}
