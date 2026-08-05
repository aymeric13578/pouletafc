<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Destination après connexion.
 *
 * Deux défauts successifs : tout le monde atterrissait sur la boutique publique,
 * puis, une fois la destination calculée, l'URL mémorisée avant la connexion
 * continuait de l'emporter — un marchand venu de /dashboard y était renvoyé et
 * se heurtait à un « 403 réservé à l'équipe ».
 *
 * Ces tests créent leur propre compte et leur propre boutique, puis les
 * suppriment. Une première version modifiait le mot de passe d'un compte
 * existant pour pouvoir se connecter : la restauration a échoué et deux comptes
 * ont perdu leur mot de passe. Un test ne doit jamais écrire sur des données
 * réelles.
 */
class RedirectionConnexionTest extends TestCase
{
    private const MOT_DE_PASSE = 'test-redirection-2026';

    private ?User $utilisateur = null;
    private ?Shop $boutique = null;

    protected function tearDown(): void
    {
        $this->boutique?->forceDelete();
        $this->utilisateur?->forceDelete();

        parent::tearDown();
    }

    private function creerUtilisateur(string $role): User
    {
        // "whatsapp" est NOT NULL sans valeur par défaut dans cette base.
        $this->utilisateur = User::create([
            'name' => 'Test redirection ' . uniqid(),
            'email' => 'test-redirection-' . uniqid() . '@example.test',
            'password' => Hash::make(self::MOT_DE_PASSE),
            'role' => $role,
            'whatsapp' => '',
        ]);

        return $this->utilisateur;
    }

    private function rattacherBoutique(User $user): Shop
    {
        $this->boutique = Shop::create([
            'shop_name' => 'Boutique de test ' . uniqid(),
            'ref' => 'TEST-' . strtoupper(substr(uniqid(), -6)),
            'slug' => 'boutique-test-' . uniqid(),
            'type' => 'INDEPENDANT',
            'status' => 'Success',
            'id_user' => $user->id,
        ]);

        return $this->boutique;
    }

    private function seConnecter(User $user, ?string $urlMemorisee = null)
    {
        if ($urlMemorisee) {
            session(['url.intended' => $urlMemorisee]);
        }

        return $this->post('/login', [
            'email' => $user->email,
            'password' => self::MOT_DE_PASSE,
        ]);
    }

    public function test_un_marchand_arrive_dans_son_espace_boutique(): void
    {
        $marchand = $this->creerUtilisateur('merchand');
        $this->rattacherBoutique($marchand);

        $this->seConnecter($marchand)->assertRedirect(route('merchand.index'));
    }

    public function test_un_marchand_venu_de_l_administration_n_y_est_pas_renvoye(): void
    {
        /*
         * Cas exact du 403 constaté : l'écran de connexion atteint depuis
         * /dashboard mémorisait cette URL, qui l'emportait ensuite sur la
         * destination du marchand.
         */
        $marchand = $this->creerUtilisateur('merchand');
        $this->rattacherBoutique($marchand);

        $this->seConnecter($marchand, url('/dashboard'))
            ->assertRedirect(route('merchand.index'));
    }

    public function test_l_equipe_interne_passe_avant_le_rattachement_boutique(): void
    {
        // Un employé peut posséder une boutique : il doit arriver sur le tableau
        // de bord, pas dans l'espace marchand.
        $employe = $this->creerUtilisateur('employee_afc');
        $this->rattacherBoutique($employe);

        $this->seConnecter($employe)->assertRedirect(route('admin.index'));
    }

    public function test_un_client_sans_boutique_reste_sur_la_boutique_publique(): void
    {
        $client = $this->creerUtilisateur('user');

        $this->seConnecter($client)->assertRedirect(url('/'));
    }

    public function test_une_url_memorisee_legitime_est_respectee(): void
    {
        // Un client qui remplit son panier puis se connecte doit revenir où il était.
        $client = $this->creerUtilisateur('user');

        $this->seConnecter($client, url('/panier'))->assertRedirect(url('/panier'));
    }
}
