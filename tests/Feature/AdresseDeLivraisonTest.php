<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Parameter;
use App\Support\PointDeLivraison;
use Tests\TestCase;

/**
 * Une commande sans adresse choisie doit porter le point de retrait désigné,
 * pas un message d'erreur.
 *
 * L'application envoie « Coordonnées non disponibles » quand le client n'a pas
 * choisi de lieu. Ce texte finissait en base et s'affichait sur le mur du
 * comptoir en guise de destination — alors que le point de retrait servait déjà
 * de repli pour les coordonnées. Les deux disaient des choses différentes.
 */
class AdresseDeLivraisonTest extends TestCase
{
    private function point(): PointDeLivraison
    {
        return app(PointDeLivraison::class);
    }

    public function test_une_adresse_choisie_est_conservee_telle_quelle(): void
    {
        $this->assertSame('Rond-point Deido', $this->point()->adresse('Rond-point Deido'));
    }

    public function test_les_messages_d_erreur_ne_sont_pas_des_adresses(): void
    {
        $lieu = Location::whereNotNull('name')->first();
        $grille = Parameter::active();

        if (! $lieu || ! $grille) {
            $this->markTestSkipped('Base sans lieu enregistré ou sans grille active.');
        }

        $ancien = $grille->default_pickup_location_id;
        $grille->update(['default_pickup_location_id' => $lieu->id]);

        try {
            foreach ([
                'Coordonnées non disponibles',
                'Adresse inconnue',
                "Erreur lors de la récupération de l'adresse",
                '',
                '   ',
            ] as $recue) {
                $retenue = $this->point()->adresse($recue);

                $this->assertNotSame($recue, $retenue, "« $recue » ne doit pas devenir une adresse.");
                $this->assertStringContainsString($lieu->name, (string) $retenue);
            }
        } finally {
            $grille->update(['default_pickup_location_id' => $ancien]);
        }
    }

    public function test_sans_point_designe_on_n_invente_rien(): void
    {
        $grille = Parameter::active();

        if (! $grille) {
            $this->markTestSkipped('Aucune grille active.');
        }

        $ancien = $grille->default_pickup_location_id;
        $grille->update(['default_pickup_location_id' => null]);

        try {
            // Sans point désigné, mieux vaut rien qu'une adresse inventée : le
            // comptoir saura qu'il doit appeler le client.
            $this->assertNull($this->point()->adresse(''));
            $this->assertNull($this->point()->nomDuLieuParDefaut());
        } finally {
            $grille->update(['default_pickup_location_id' => $ancien]);
        }
    }
}
