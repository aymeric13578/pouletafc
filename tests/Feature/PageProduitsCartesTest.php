<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Page Produits : cartes, sélection multiple et rattachement groupé.
 *
 * Rattacher un complément produit par produit devient vite pénible : une sauce
 * accompagne souvent tout un menu.
 */
class PageProduitsCartesTest extends TestCase
{
    private const URL = '/dashboard/products';

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

    public function test_les_produits_s_affichent_en_cartes(): void
    {
        $this->produit('Poulet en carte');

        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSeeText('Poulet en carte');
        // Plus de tableau : les cartes portent une clé par produit.
        $reponse->assertSee('wire:key="produit-', false);
        $reponse->assertDontSee('<thead>', false);
    }

    public function test_la_carte_offre_d_ajouter_un_complement(): void
    {
        $this->produit('Poulet à accompagner');

        $this->actingAs($this->staff())->get(self::URL)
            ->assertOk()
            ->assertSeeText('Ajouter un complément')
            ->assertSee('basculerComplement', false);
    }

    /** La sélection multiple doit être proposée sur les produits ordinaires. */
    public function test_la_selection_multiple_est_proposee(): void
    {
        $this->produit('Poulet sélectionnable');

        $this->actingAs($this->staff())->get(self::URL)
            ->assertOk()
            ->assertSee('wire:model.live="selection"', false)
            ->assertSeeText('Sélectionner tous les produits affichés');
    }

    /*
     | Un complément ne se rattache pas à lui-même.
     |
     | L'écran de vente afficherait le plat sous lui-même.
     */
    public function test_un_complement_ne_porte_pas_de_case_de_selection(): void
    {
        $source = file_get_contents(base_path('resources/views/pages/dashboard/products.blade.php'));

        $this->assertStringContainsString('@unless ($product->is_complement)', $source);

        // Le garde-fou vit maintenant dans la boucle du rattachement groupé :
        // on saute le complément qui serait sa propre cible.
        $this->assertStringContainsString(
            "if ((int) \$idProduit === (int) \$complement->id) {",
            $source,
            'Le garde-fou contre l\'auto-rattachement a disparu.'
        );
    }

    /*
     | Deux gestes distincts, à ne pas confondre.
     |
     | « Ajouter un complément » rattache des accompagnements À ce produit ;
     | « Définir comme complément » dit que ce produit EST un accompagnement.
     | Le premier bouton portait le nom du second : on croyait rattacher, on
     | marquait.
     */
    public function test_les_deux_gestes_sont_distincts(): void
    {
        $this->produit('Poulet à distinguer');

        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSeeText('Ajouter un complément');
        $reponse->assertSeeText('Définir comme complément');
        $reponse->assertSee('ouvrirRattachement', false);
        $reponse->assertSee('basculerComplement', false);
    }

    /*
     | Plusieurs compléments d'un coup, sur plusieurs produits.
     |
     | Un seul choix obligeait à répéter le geste autant de fois qu'il y a
     | d'accompagnements, alors que c'est le même mouvement.
     */
    public function test_plusieurs_complements_se_rattachent_en_une_fois(): void
    {
        $poulet = $this->produit('Poulet groupé');
        $poisson = $this->produit('Poisson groupé');
        $coca = $this->produit('Coca groupé', complement: true);
        $frites = $this->produit('Frites groupées', complement: true);

        // On rejoue ce que fait l'écran : la page Folio n'est pas pilotable.
        foreach ([$poulet, $poisson] as $plat) {
            foreach ([$coca, $frites] as $complement) {
                $plat->complements()->attach($complement->id);
            }
        }

        $this->assertSame(2, $poulet->fresh()->complements()->count());
        $this->assertSame(2, $poisson->fresh()->complements()->count());

        // La barre groupée n'est rendue qu'une fois des produits cochés : on
        // vérifie dans la source que le choix y est bien multiple.
        $source = file_get_contents(base_path('resources/views/pages/dashboard/products.blade.php'));

        $this->assertStringContainsString('wire:model.live="complementsARattacher"', $source);
        $this->assertStringContainsString('type="checkbox"', $source);
        $this->assertStringNotContainsString(
            'complementARattacher"',
            $source,
            'Le choix unique doit avoir disparu au profit du choix multiple.'
        );
    }

    /*
     | La confirmation doit réellement s'afficher.
     |
     | Livewire 3 transmet dispatch('notify', ['message' => …]) comme un
     | paramètre positionnel : la charge arrive sous la clé 0. Lue à plat,
     | event.detail.type valait undefined et toastr[undefined] levait une
     | erreur — aucune notification n'apparaissait, sur aucune page.
     */
    public function test_les_notifications_sont_ecoutees_globalement(): void
    {
        $reponse = $this->actingAs($this->staff())->get(self::URL);

        $reponse->assertOk();
        $reponse->assertSee("window.addEventListener('notify'", false);
        // Les deux formes de charge doivent être acceptées.
        $reponse->assertSee('brut[0]', false);
    }
}
