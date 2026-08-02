<?php
namespace Tests\Feature;
use Tests\TestCase;

/** Garde-fou : chaque écran du back-office doit rendre sans erreur. */
class AdminRenderTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'accueil' => ['/dashboard'],
            'produits' => ['/dashboard/products'],
            'categories' => ['/dashboard/categories'],
            'sous-categories' => ['/dashboard/sub-categories'],
            'articles' => ['/dashboard/articles'],
            'commandes' => ['/dashboard/commandes'],
            'transactions' => ['/dashboard/transactions'],
            'clando' => ['/dashboard/clando'],
            'clients' => ['/dashboard/customers'],
            'agents' => ['/dashboard/agents'],
            'operateurs' => ['/dashboard/operators'],
            'utilisateurs' => ['/dashboard/users'],
            'statistiques' => ['/dashboard/statistiques'],
        ];
    }

    /** @dataProvider pages */
    public function test_ecran_rend_sans_erreur(string $url): void
    {
        $user = \App\Models\User::where("role", "admin")->firstOrFail();
        $this->actingAs($user)->get($url)->assertOk();
    }

    public function test_la_navigation_laterale_expose_tous_les_ecrans(): void
    {
        $user = \App\Models\User::where("role", "admin")->firstOrFail();
        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        foreach (['Produits', 'Catégories', 'Commandes', 'Transactions', 'Clients', 'Agents', 'Utilisateurs', 'Statistiques'] as $lien) {
            $this->assertStringContainsString($lien, $html, "Le lien « $lien » doit être dans la navigation.");
        }
    }

    public function test_l_accueil_affiche_des_indicateurs_et_non_une_grille_de_liens(): void
    {
        $user = \App\Models\User::where("role", "admin")->firstOrFail();
        $response = $this->actingAs($user)->get('/dashboard');

        // assertSee échappe par défaut, ce qui correspond au rendu Blade (apostrophes en &#039;).
        $response->assertSee("Vue d'ensemble");
        $response->assertSee("Chiffre d'affaires");
        $response->assertSee('Dernières commandes');
        $response->assertSee('Stock à surveiller');
    }
}
