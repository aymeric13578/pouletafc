<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Support\PointDeLivraison;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Où livrer une commande.
 *
 * La création copiait users.latitude/longitude — la dernière position connue du
 * téléphone, écrite une fois et quasiment jamais remise à jour. L'adresse
 * choisie au panier n'était gardée qu'en texte, ses coordonnées jetées. En
 * production, 65 clients sur 74 avaient toujours le même point de livraison
 * quelle que soit l'adresse choisie.
 */
class PointDeLivraisonTest extends TestCase
{
    /**
     * Un lieu réellement situé au Cameroun.
     *
     * La base de développement contient des enregistrements d'essai hors zone —
     * « garoua test » à 2,0 / 3,33 — que le résolveur écarte à raison. Les
     * prendre comme référence ferait échouer le test pour la bonne raison.
     */
    private function lieuExploitable(): ?Location
    {
        // latitude et longitude sont des varchar : un whereBetween les compare
        // comme des chaînes et ne retient presque rien. On filtre en PHP.
        return Location::whereNotNull('latitude')->whereNotNull('longitude')->get()
            ->first(fn (Location $l) => is_numeric($l->latitude) && is_numeric($l->longitude)
                && $l->latitude >= 1.6 && $l->latitude <= 13.1
                && $l->longitude >= 8.4 && $l->longitude <= 16.2);
    }

    private function resoudre(array $entrees, ?User $user = null): array
    {
        return (new PointDeLivraison())->resoudre(
            Request::create('/', 'POST', $entrees),
            $user,
        );
    }

    /** Un client dont le téléphone a été localisé une fois, ailleurs. */
    private function clientAvecPosition(float $lat = 9.2500, float $lon = 13.3000): User
    {
        $user = User::query()->firstOrFail();
        $user->latitude = $lat;
        $user->longitude = $lon;

        return $user;
    }

    public function test_les_coordonnees_transmises_priment_sur_tout(): void
    {
        [$lat, $lon, $origine] = $this->resoudre(
            ['delivery_lat' => 9.3050, 'delivery_lon' => 13.3950],
            $this->clientAvecPosition(),
        );

        $this->assertEqualsWithDelta(9.3050, $lat, 0.0001);
        $this->assertEqualsWithDelta(13.3950, $lon, 0.0001);
        $this->assertSame('transmis', $origine);
    }

    public function test_le_lieu_choisi_est_retrouve_par_son_nom(): void
    {
        /*
         * C'est le cas qui répare l'existant sans toucher à l'application : la
         * recherche d'adresse du panier propose ces noms, celui qui revient
         * correspond donc à une ligne de la table des lieux.
         */
        $lieu = $this->lieuExploitable();

        if (! $lieu) {
            $this->markTestSkipped('Aucun lieu avec coordonnées dans la base de développement.');
        }

        [$lat, $lon, $origine] = $this->resoudre(
            ['delivery_address' => $lieu->name],
            $this->clientAvecPosition(),
        );

        $this->assertSame('lieu_nom', $origine, 'Le lieu doit primer sur la position du téléphone.');
        $this->assertEqualsWithDelta((float) $lieu->latitude, $lat, 0.0001);
    }

    public function test_la_recherche_par_nom_ignore_la_casse_et_les_espaces(): void
    {
        $lieu = $this->lieuExploitable();

        if (! $lieu) {
            $this->markTestSkipped('Aucun lieu avec coordonnées.');
        }

        [, , $origine] = $this->resoudre(
            ['delivery_address' => '  ' . mb_strtoupper($lieu->name) . '  '],
        );

        $this->assertSame('lieu_nom', $origine);
    }

    public function test_une_adresse_inconnue_retombe_sur_la_position_du_telephone(): void
    {
        // « Coordonnées non disponibles » : 34 commandes en production portent
        // littéralement ce texte en guise d'adresse.
        [$lat, $lon, $origine] = $this->resoudre(
            ['delivery_address' => 'Coordonnées non disponibles'],
            $this->clientAvecPosition(9.2500, 13.3000),
        );

        $this->assertSame('position_client', $origine);
        $this->assertEqualsWithDelta(9.2500, $lat, 0.0001);
    }

    public function test_des_coordonnees_interverties_sont_remises_dans_l_ordre(): void
    {
        /*
         * 45 commandes en production portent une latitude et une longitude
         * échangées, ce qui place la livraison à 13,4 N / 9,3 E — en plein
         * Niger au lieu de Garoua.
         */
        [$lat, $lon, $origine] = $this->resoudre([
            'delivery_lat' => 13.3992,
            'delivery_lon' => 9.2980,
        ]);

        $this->assertEqualsWithDelta(9.2980, $lat, 0.0001, 'La latitude doit revenir à ~9,3');
        $this->assertEqualsWithDelta(13.3992, $lon, 0.0001, 'La longitude doit revenir à ~13,4');
        $this->assertSame('transmis', $origine);
    }

    public function test_un_point_deja_correct_n_est_jamais_touche(): void
    {
        // L'échange ne s'applique que s'il lève l'ambiguïté. Un point valide
        // reste tel quel, même si l'échange donnerait aussi un point valide.
        [$lat, $lon] = $this->resoudre([
            'delivery_lat' => 9.3000,
            'delivery_lon' => 13.4000,
        ]);

        $this->assertEqualsWithDelta(9.3000, $lat, 0.0001);
        $this->assertEqualsWithDelta(13.4000, $lon, 0.0001);
    }

    public function test_un_point_hors_zone_irrecuperable_est_ecarte(): void
    {
        /*
         * Paris. Ni le point ni son échange ne tombent au Cameroun : on passe à
         * la source suivante plutôt que d'envoyer un livreur à six mille
         * kilomètres.
         */
        [$lat, $lon, $origine] = $this->resoudre(
            ['delivery_lat' => 48.85, 'delivery_lon' => 2.35],
            $this->clientAvecPosition(9.2500, 13.3000),
        );

        $this->assertSame('position_client', $origine);
        $this->assertEqualsWithDelta(9.2500, $lat, 0.0001);
    }

    public function test_un_zero_ne_vaut_pas_une_coordonnee(): void
    {
        // Le zéro tombe au large du golfe de Guinée : il signifie « rien de
        // relevé », pas un point de livraison.
        [$lat, $lon, $origine] = $this->resoudre(
            ['delivery_lat' => 0, 'delivery_lon' => 0],
            $this->clientAvecPosition(9.2500, 13.3000),
        );

        $this->assertSame('position_client', $origine);
    }

    public function test_sans_aucune_source_le_point_reste_vide(): void
    {
        [$lat, $lon, $origine] = $this->resoudre([], null);

        $this->assertNull($lat);
        $this->assertNull($lon);
        $this->assertSame('aucune', $origine);
    }

    public function test_le_lieu_par_identifiant_prime_sur_le_nom(): void
    {
        $lieu = $this->lieuExploitable();

        if (! $lieu) {
            $this->markTestSkipped('Aucun lieu avec coordonnées.');
        }

        [, , $origine] = $this->resoudre([
            'id_location' => $lieu->id,
            'delivery_address' => 'Adresse qui ne correspond à rien',
        ]);

        $this->assertSame('lieu_id', $origine);
    }
}
