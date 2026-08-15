<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Parameter;
use App\Models\Quarter;
use App\Models\User;
use App\Support\PointDeLivraison;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Désignation du point de livraison par défaut depuis l'écran Lieux.
 *
 * Le réglage existait côté serveur mais aucun écran ne permettait de le poser :
 * la fonctionnalité était donc inaccessible, ce qui revenait à ne pas l'avoir.
 */
class LieuParDefautEcranTest extends TestCase
{
    private const URL = '/dashboard/lieux';

    private ?int $idInitial = null;
    private ?Location $lieu = null;
    private ?Location $sansCoordonnees = null;

    private function staff(): User
    {
        $staff = User::first();

        if (! $staff) {
            $this->markTestSkipped('Aucun utilisateur en base.');
        }

        $staff->role = 'admin';
        $staff->save();

        return $staff;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $grille = Parameter::active();

        if (! $grille) {
            $this->markTestSkipped('Aucune configuration active.');
        }

        $this->idInitial = $grille->default_pickup_location_id;

        $quartier = Quarter::first() ?? Quarter::create(['name' => 'Quartier essai', 'status' => 'Success']);

        $this->lieu = Location::create([
            'id_quarter' => $quartier->id,
            'name' => 'Dépôt essai',
            'latitude' => '9.3017',
            'longitude' => '13.3921',
            'status' => 'Success',
        ]);

        $this->sansCoordonnees = Location::create([
            'id_quarter' => $quartier->id,
            'name' => 'Lieu sans position',
            'status' => 'Success',
        ]);
    }

    protected function tearDown(): void
    {
        Parameter::active()?->update(['default_pickup_location_id' => $this->idInitial]);
        $this->lieu?->delete();
        $this->sansCoordonnees?->delete();

        parent::tearDown();
    }

    public function test_l_ecran_propose_de_definir_un_lieu_par_defaut(): void
    {
        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSeeText('Définir par défaut');
        $reponse->assertSee('definirParDefaut', false);
    }

    /*
     | Désigner un lieu doit réellement changer la résolution.
     |
     | Un bouton qui écrit en base sans que le calcul le lise donnerait
     | l'illusion du réglage.
     */
    public function test_designer_un_lieu_change_le_point_resolu(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => $this->lieu->id]);

        [$lat, $lon, $origine] = (new PointDeLivraison())->resoudre(new Request(), null);

        $this->assertSame('lieu_par_defaut', $origine);
        $this->assertEqualsWithDelta(9.3017, $lat, 0.0001);
        $this->assertEqualsWithDelta(13.3921, $lon, 0.0001);
    }

    /*
     | Un lieu sans coordonnées ne doit pas pouvoir être désigné.
     |
     | Il enverrait le livreur au large du golfe de Guinée, et l'erreur ne se
     | découvrirait qu'à la livraison.
     */
    public function test_un_lieu_sans_coordonnees_ne_peut_pas_etre_designe(): void
    {
        $reponse = $this->actingAs($this->staff())->get(self::URL);
        $reponse->assertOk();

        // L'écran le signale au lieu d'offrir le bouton.
        $reponse->assertSeeText('Coordonnées requises');

        // Et le calcul ne retiendrait pas ce lieu même s'il était forcé en base.
        Parameter::active()->update(['default_pickup_location_id' => $this->sansCoordonnees->id]);

        [$lat, , $origine] = (new PointDeLivraison())->resoudre(new Request(), null);

        $this->assertNull($lat);
        $this->assertSame('aucune', $origine);
    }

    /** Le bandeau doit dire quel lieu est retenu, sans parcourir le tableau. */
    public function test_le_bandeau_annonce_le_lieu_retenu(): void
    {
        Parameter::active()->update(['default_pickup_location_id' => null]);
        $this->actingAs($this->staff())->get(self::URL)
            ->assertSeeText('Aucun point de livraison par défaut');

        Parameter::active()->update(['default_pickup_location_id' => $this->lieu->id]);
        $this->actingAs($this->staff())->get(self::URL)
            ->assertSeeText('Dépôt essai');
    }
}
