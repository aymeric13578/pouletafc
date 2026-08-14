<?php

namespace Tests\Feature;

use App\Models\order_detail;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Validation du code de livraison à la remise du colis.
 *
 * L'application envoie le paramètre « code », le serveur ne lisait que
 * « delivery_code » : la valeur reçue était donc toujours nulle, la comparaison
 * échouait à chaque tentative, et aucun agent ne pouvait terminer une livraison.
 * L'écran répondait « code incorrect !!! 3 essai restants » quel que soit le
 * code saisi — et ce décompte n'existait même pas côté serveur.
 */
class CodeLivraisonTest extends TestCase
{
    private const URL = '/api/v1.0/terminatedCourseOrder';

    private function commande(): order_detail
    {
        return order_detail::orderByDesc('id')->firstOrFail();
    }

    private function preparer(order_detail $c, string $code): array
    {
        $etat = ['status' => $c->status, 'delivery_code' => $c->delivery_code];

        DB::table('order_details')->where('id', $c->id)->update([
            'status' => 'process',
            'delivery_code' => $code,
        ]);

        return $etat;
    }

    public function test_le_code_envoye_par_l_application_est_accepte(): void
    {
        // L'application envoie « code », pas « delivery_code ».
        $commande = $this->commande();
        $etat = $this->preparer($commande, '4271');

        $this->getJson(self::URL . "?ref={$commande->ref}&code=4271")
            ->assertOk()
            ->assertJson(['response' => 200]);

        $this->assertSame('Success', $commande->fresh()->status);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_l_ancien_nom_de_parametre_reste_accepte(): void
    {
        // Les téléphones déjà installés continuent d'envoyer l'un ou l'autre.
        $commande = $this->commande();
        $etat = $this->preparer($commande, '5382');

        $this->getJson(self::URL . "?ref={$commande->ref}&delivery_code=5382")
            ->assertOk()
            ->assertJson(['response' => 200]);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_un_code_faux_est_refuse(): void
    {
        $commande = $this->commande();
        $etat = $this->preparer($commande, '4271');

        $reponse = $this->getJson(self::URL . "?ref={$commande->ref}&code=9999")->assertOk();

        $this->assertSame(400, $reponse->json('response'));
        // Le message n'annonce plus un quota de tentatives qui n'existe pas.
        $this->assertStringNotContainsString('essai restants', $reponse->json('message'));
        $this->assertSame('process', $commande->fresh()->status);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_un_code_absent_ne_valide_rien(): void
    {
        /*
         * Le point le plus dangereux : sans code transmis, la comparaison lâche
         * d'origine aurait pu confondre null et une valeur vide, et clôturer une
         * livraison que personne n'a reçue.
         */
        $commande = $this->commande();
        $etat = $this->preparer($commande, '4271');

        $this->getJson(self::URL . "?ref={$commande->ref}")
            ->assertOk()
            ->assertJson(['response' => 400]);

        $this->assertSame('process', $commande->fresh()->status);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_une_reference_inconnue_ne_fait_pas_planter_le_serveur(): void
    {
        $this->getJson(self::URL . '?ref=INEXISTANT&code=1234')
            ->assertOk()
            ->assertJson(['response' => 400]);
    }
}
