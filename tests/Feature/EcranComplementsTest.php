<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Écran d'administration des compléments.
 *
 * On n'y crée pas de produit : on désigne ceux qui peuvent accompagner, et on
 * les rattache aux plats. Le catalogue reste la page Produits.
 */
class EcranComplementsTest extends TestCase
{
    private const URL = '/dashboard/complements';

    private array $produits = [];

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

    private function produit(string $nom, bool $complement = false): Product
    {
        $produit = Product::create([
            'name' => $nom, 'price' => 1000, 'stock_init' => 10,
            'status' => 'Success', 'is_complement' => $complement,
        ]);

        $this->produits[] = $produit->id;

        return $produit;
    }

    protected function tearDown(): void
    {
        DB::table('product_complement')
            ->whereIn('product_id', $this->produits)
            ->orWhereIn('complement_id', $this->produits)
            ->delete();

        Product::whereIn('id', $this->produits)->forceDelete();

        parent::tearDown();
    }

    public function test_l_ecran_est_reserve_a_l_equipe(): void
    {
        $visiteur = $this->staff();
        $role = $visiteur->role;
        $visiteur->role = 'user';
        $visiteur->save();

        try {
            $this->actingAs($visiteur)->get(self::URL)->assertForbidden();
        } finally {
            $visiteur->role = $role;
            $visiteur->save();
        }
    }

    public function test_l_ecran_propose_de_designer_et_de_rattacher(): void
    {
        $this->produit('Frites', complement: true);

        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSee('basculerComplement', false);
        // Les deux vues sont proposées dans le sélecteur ; le rattachement
        // n'est rendu que dans la seconde, qui n'est pas celle d'arrivée.
        $reponse->assertSeeText('Rattacher à un produit');
        $this->assertStringContainsString(
            'basculerLien',
            file_get_contents(base_path('resources/views/pages/dashboard/complements.blade.php'))
        );
    }

    /*
     | Retirer le drapeau défait les rattachements.
     |
     | Les laisser en place ferait proposer, sur l'écran de vente, un produit
     | qui n'est plus censé accompagner quoi que ce soit.
     */
    public function test_retirer_le_drapeau_defait_les_rattachements(): void
    {
        $poulet = $this->produit('Poulet');
        $frites = $this->produit('Frites', complement: true);
        $poulet->complements()->attach($frites->id);

        $this->assertSame(1, $poulet->complements()->count());

        // On rejoue ce que fait l'écran, la page Folio n'étant pas pilotable.
        $frites->update(['is_complement' => false]);
        $frites->proposePar()->detach();

        $this->assertSame(0, $poulet->fresh()->complements()->count());
    }

    /** Un produit ne peut pas être son propre complément. */
    public function test_un_produit_ne_se_propose_pas_lui_meme(): void
    {
        $source = file_get_contents(base_path('resources/views/pages/dashboard/complements.blade.php'));

        $this->assertStringContainsString(
            "(int) \$idProduit === (int) \$idComplement",
            $source,
            'Le garde-fou contre l\'auto-rattachement a disparu.'
        );
    }

    /** Le menu doit mener à l'écran, sinon il reste introuvable. */
    public function test_le_menu_mene_a_l_ecran(): void
    {
        $this->actingAs($this->staff())->get('/dashboard/products')
            ->assertOk()
            ->assertSee('dashboard/complements', false);
    }
}
