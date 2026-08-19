<?php

namespace Tests\Feature;

use App\Support\CommandeSansDoublon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un client qui réappuie ne doit pas commander deux fois.
 *
 * Constaté en production : trois commandes identiques d'un même client, paniers
 * 542, 543 et 544, créés à 36 secondes d'intervalle, un seul produit à chaque
 * fois. Le panier était fermé dès le début de la création de commande ; l'écran
 * en ouvrait donc un neuf, et ce panier neuf redonnait un identifiant inédit que
 * le contrôle de doublon — qui comparait (client, panier) — ne pouvait plus
 * reconnaître.
 *
 * Ces cas créent leurs propres lignes et les effacent ensuite : la configuration
 * de test pointe sur la base de développement, qu'un rafraîchissement viderait.
 */
class CommandeEnDoubleTest extends TestCase
{
    private array $paniers = [];

    private array $commandes = [];

    private int $client = 999000001;

    protected function tearDown(): void
    {
        if ($this->commandes) {
            DB::table('order_details')->whereIn('id', $this->commandes)->delete();
        }

        if ($this->paniers) {
            DB::table('cart_items')->whereIn('cart_id', $this->paniers)->delete();
            DB::table('carts')->whereIn('id', $this->paniers)->delete();
        }

        parent::tearDown();
    }

    /** Un panier portant les articles donnés, sous la forme produit => quantité. */
    private function panier(array $articles): int
    {
        $id = DB::table('carts')->insertGetId([
            'user_id' => $this->client,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->paniers[] = $id;

        foreach ($articles as $produit => $quantite) {
            DB::table('cart_items')->insert([
                'user_id' => $this->client,
                'cart_id' => $id,
                'product_id' => $produit,
                'quantity' => $quantite,
                'amount' => 1000,
                'status' => 'Success',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function commande(int $idPanier, int $prix = 3500, ?string $adresse = 'Akwa'): int
    {
        $id = DB::table('order_details')->insertGetId([
            'id_user' => $this->client,
            'id_cart' => $idPanier,
            'ref' => 'TEST_' . uniqid(),
            'price' => $prix,
            'status' => 'pending',
            'address' => $adresse,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->commandes[] = $id;

        return $id;
    }

    public function test_un_second_panier_au_meme_contenu_est_reconnu(): void
    {
        // C'est exactement la situation observée : deux paniers distincts, le
        // même produit dans chacun, à quelques secondes d'intervalle.
        $premier = $this->panier([8 => 1]);
        $this->commande($premier);

        $second = $this->panier([8 => 1]);

        $trouvee = (new CommandeSansDoublon())->dejaPassee($this->client, $second, 3500, 'Akwa');

        $this->assertNotNull($trouvee, 'Le doublon aurait dû être reconnu par le contenu du panier.');
        $this->assertSame($premier, (int) $trouvee->id_cart);
    }

    public function test_le_meme_panier_reste_reconnu(): void
    {
        // Le contrôle d'origine, qu'on ne casse pas en le remplaçant.
        $panier = $this->panier([8 => 1]);
        $this->commande($panier);

        $this->assertNotNull(
            (new CommandeSansDoublon())->dejaPassee($this->client, $panier, 3500, 'Akwa')
        );
    }

    public function test_un_panier_different_passe(): void
    {
        // Une vraie seconde commande ne doit pas être avalée par le garde-fou :
        // c'est le risque exact de ce genre de protection.
        $premier = $this->panier([8 => 1]);
        $this->commande($premier);

        $autre = $this->panier([8 => 2]);

        $this->assertNull(
            (new CommandeSansDoublon())->dejaPassee($this->client, $autre, 3500, 'Akwa'),
            'Deux quantités différentes sont deux commandes différentes.'
        );
    }

    public function test_l_ordre_des_articles_ne_trompe_pas_le_controle(): void
    {
        $premier = $this->panier([8 => 1, 12 => 2]);
        $this->commande($premier);

        // Mêmes articles, saisis dans l'autre sens.
        $second = $this->panier([12 => 2, 8 => 1]);

        $this->assertNotNull(
            (new CommandeSansDoublon())->dejaPassee($this->client, $second, 3500, 'Akwa')
        );
    }

    public function test_une_commande_ancienne_ne_bloque_plus_rien(): void
    {
        $premier = $this->panier([8 => 1]);
        $id = $this->commande($premier);

        // Une heure plus tôt : le client a le droit de recommander la même chose.
        DB::table('order_details')->where('id', $id)->update(['created_at' => now()->subHour()]);

        $second = $this->panier([8 => 1]);

        $this->assertNull(
            (new CommandeSansDoublon())->dejaPassee($this->client, $second, 3500, 'Akwa')
        );
    }

    public function test_la_cle_reconnait_exactement_la_meme_tentative(): void
    {
        $panier = $this->panier([8 => 1]);
        $id = $this->commande($panier);

        DB::table('order_details')->where('id', $id)->update(['cle_unique' => 'essai-cle-1']);

        $this->assertNotNull(
            (new CommandeSansDoublon())->parCle('essai-cle-1'),
            'Un second envoi sous la même clé est la même commande.'
        );

        $this->assertNull((new CommandeSansDoublon())->parCle('essai-cle-2'));
        $this->assertNull((new CommandeSansDoublon())->parCle(''));
    }

    public function test_deux_commandes_voulues_passent_si_les_cles_different(): void
    {
        /*
        | Le vrai risque de ce genre de garde-fou est d'avaler une commande
        | légitime. Avec une clé, deux tentatives distinctes restent distinctes,
        | même si elles portent exactement le même panier.
        */
        $premier = $this->panier([8 => 1]);
        $id = $this->commande($premier);
        DB::table('order_details')->where('id', $id)->update(['cle_unique' => 'tentative-A']);

        $this->assertNull(
            (new CommandeSansDoublon())->parCle('tentative-B'),
            "Une seconde tentative assumée doit pouvoir aboutir."
        );
    }

    public function test_une_course_sans_panier_est_reconnue_par_son_trajet(): void
    {
        // Les courses de coursier n'ont pas de panier : seuls le prix et
        // l'adresse les décrivent.
        $this->commande(0, 2500, 'Bonabéri');
        DB::table('order_details')->where('id', end($this->commandes))->update(['id_cart' => null]);

        $trouvee = (new CommandeSansDoublon())->dejaPassee($this->client, null, 2500, 'Bonabéri');

        $this->assertNotNull($trouvee);

        $this->assertNull(
            (new CommandeSansDoublon())->dejaPassee($this->client, null, 2500, 'Deido'),
            "Une autre destination est une autre course."
        );
    }
}
