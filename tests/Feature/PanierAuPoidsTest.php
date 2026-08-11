<?php

namespace Tests\Feature;

use App\Models\order_detail;
use App\Models\Parameter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correction d'un panier au poids réel depuis le mur des commandes.
 *
 * Un poulet n'est pesé qu'à la préparation : le montant commandé ne correspond
 * presque jamais au poids servi, et l'écart se réglait de la main à la main,
 * sans trace. Le comptoir saisit le poids, le montant suit.
 */
class PanierAuPoidsTest extends TestCase
{
    private function url(int $id): string
    {
        return "/commandes/{$id}/panier";
    }

    private function commande(): order_detail
    {
        return order_detail::orderByDesc('id')->firstOrFail();
    }

    private function etat(order_detail $c): array
    {
        return [
            'status' => $c->status,
            'price' => $c->price,
            'panier_price' => $c->panier_price,
            'poids_kg' => $c->poids_kg,
            'delivery_fees' => $c->delivery_fees,
        ];
    }

    /** Fixe un tarif au kilo sur la grille active, et rend de quoi la restaurer. */
    private function fixerTarif(?int $tarif): array
    {
        $active = Parameter::active();

        if (! $active) {
            $this->markTestSkipped('Aucune grille tarifaire active dans la base de développement.');
        }

        $ancien = $active->price_per_kg;
        DB::table('parameters')->where('id', $active->id)->update(['price_per_kg' => $tarif]);

        return [$active->id, $ancien];
    }

    public function test_le_poids_saisi_recalcule_le_panier_et_le_total(): void
    {
        [$idGrille, $ancienTarif] = $this->fixerTarif(2500);

        $commande = $this->commande();
        $etat = $this->etat($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'delivery_fees' => 500,
        ]);

        $reponse = $this->postJson($this->url($commande->id), ['poids_kg' => 1.5]);

        $reponse->assertOk();
        $this->assertTrue($reponse->json('action.ok'));

        $frais = $commande->fresh();
        $this->assertSame(3750, (int) $frais->panier_price, '1,5 kg × 2 500 F');
        // Le total suit la règle de création : panier + frais de livraison.
        $this->assertSame(4250, (int) $frais->price, 'panier + livraison');
        $this->assertEqualsWithDelta(1.5, (float) $frais->poids_kg, 0.001);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        DB::table('parameters')->where('id', $idGrille)->update(['price_per_kg' => $ancienTarif]);
    }

    public function test_sans_tarif_au_kilo_la_correction_est_refusee(): void
    {
        [$idGrille, $ancienTarif] = $this->fixerTarif(null);

        $commande = $this->commande();
        $etat = $this->etat($commande);
        DB::table('order_details')->where('id', $commande->id)->update(['status' => 'pending']);

        $reponse = $this->postJson($this->url($commande->id), ['poids_kg' => 2]);

        $reponse->assertStatus(422);
        $this->assertFalse($reponse->json('action.ok'));
        // Le message doit dire où corriger, pas seulement que c'est impossible.
        $this->assertStringContainsString('Configuration', $reponse->json('action.message'));
        $this->assertSame($etat['price'], $commande->fresh()->price);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        DB::table('parameters')->where('id', $idGrille)->update(['price_per_kg' => $ancienTarif]);
    }

    public function test_une_commande_close_ne_se_corrige_plus(): void
    {
        /*
         * Le montant a été encaissé et la commission calculée dessus : le
         * modifier après coup fausserait les deux.
         */
        [$idGrille, $ancienTarif] = $this->fixerTarif(2500);

        $commande = $this->commande();
        $etat = $this->etat($commande);
        DB::table('order_details')->where('id', $commande->id)->update(['status' => 'Success']);

        $reponse = $this->postJson($this->url($commande->id), ['poids_kg' => 3]);

        $reponse->assertStatus(409);
        $this->assertFalse($reponse->json('action.ok'));
        $this->assertSame($etat['price'], $commande->fresh()->price);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
        DB::table('parameters')->where('id', $idGrille)->update(['price_per_kg' => $ancienTarif]);
    }

    public function test_un_poids_nul_ou_negatif_est_refuse(): void
    {
        $commande = $this->commande();

        foreach ([0, -2, 'abc'] as $valeur) {
            $this->postJson($this->url($commande->id), ['poids_kg' => $valeur])->assertStatus(422);
        }

        $this->postJson($this->url($commande->id), [])->assertStatus(422);
    }

    public function test_le_flux_expose_la_decomposition_du_prix(): void
    {
        $commande = $this->commande();
        $etat = $this->etat($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'panier_price' => 3000,
            'delivery_fees' => 700,
            'price' => 3700,
        ]);

        $charge = $this->getJson('/commandes/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertNotNull($trouvee);
        $this->assertSame(3000, $trouvee['panier_price']);
        $this->assertSame(700, $trouvee['delivery_fees']);
        $this->assertSame(3700, $trouvee['price']);
        $this->assertTrue($trouvee['panier_modifiable']);

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_une_commande_ancienne_sans_panier_price_retombe_sur_la_soustraction(): void
    {
        /*
         * Les commandes créées avant que la colonne soit renseignée ont un
         * panier_price nul. Afficher « 0 F » de panier à côté d'un total de
         * 5 000 F ferait croire à une commande entièrement composée de frais.
         */
        $commande = $this->commande();
        $etat = $this->etat($commande);

        DB::table('order_details')->where('id', $commande->id)->update([
            'status' => 'pending',
            'panier_price' => null,
            'delivery_fees' => 500,
            'price' => 4000,
        ]);

        $charge = $this->getJson('/commandes/flux')->assertOk()->json();
        $trouvee = collect($charge['orders'])->firstWhere('id', $commande->id);

        $this->assertSame(3500, $trouvee['panier_price'], 'total moins frais de livraison');

        DB::table('order_details')->where('id', $commande->id)->update($etat);
    }

    public function test_le_flux_expose_le_prix_du_kilo(): void
    {
        [$idGrille, $ancienTarif] = $this->fixerTarif(3200);

        $this->assertSame(3200, $this->getJson('/commandes/flux')->assertOk()->json('price_per_kg'));

        $this->fixerTarif(null);
        $this->assertNull($this->getJson('/commandes/flux')->assertOk()->json('price_per_kg'));

        DB::table('parameters')->where('id', $idGrille)->update(['price_per_kg' => $ancienTarif]);
    }

    public function test_le_prix_du_kilo_est_enregistrable_dans_la_configuration(): void
    {
        $active = Parameter::active();

        if (! $active) {
            $this->markTestSkipped('Aucune grille active.');
        }

        $ancien = $active->price_per_kg;

        $active->update(['price_per_kg' => 2750]);
        $this->assertSame(2750, (int) $active->fresh()->price_per_kg);

        // Facultatif : une grille sans tarif doit rester enregistrable.
        $active->update(['price_per_kg' => null]);
        $this->assertNull($active->fresh()->price_per_kg);

        DB::table('parameters')->where('id', $active->id)->update(['price_per_kg' => $ancien]);
    }
}
