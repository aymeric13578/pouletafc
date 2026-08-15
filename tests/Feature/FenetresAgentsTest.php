<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Fenêtres de l'écran Agents.
 *
 * Les trois calques — fiche agent, pièce jointe, crédit — sont empilés au même
 * endroit et ne se fermaient qu'à leur propre bouton. Il suffisait qu'un reste
 * ouvert pour qu'en ouvrir un second les superpose : s'affichait alors celui
 * qui vient le plus bas dans la page, et non celui qu'on venait de demander.
 *
 * Le composant vit dans une page Folio et n'est pas adressable par
 * Livewire\Volt\Volt::test : ces vérifications portent donc sur le rendu et sur
 * la source. C'est moins qu'un pilotage réel, mais cela suffit à empêcher le
 * retour exact du défaut — une fenêtre ouverte sans fermer les autres.
 */
class FenetresAgentsTest extends TestCase
{
    private const ECRAN = 'resources/views/pages/dashboard/agents.blade.php';

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

    private function source(): string
    {
        return file_get_contents(base_path(self::ECRAN));
    }

    /**
     * Toute méthode qui ouvre une fenêtre doit d'abord fermer les autres.
     *
     * Écrit comme une garde plutôt que comme un exemple : le jour où une
     * quatrième fenêtre apparaîtra, ce test la réclamera aussi.
     */
    public function test_toute_ouverture_ferme_les_autres_fenetres(): void
    {
        $source = $this->source();

        preg_match_all(
            '/public function (open\w+)\((.*?)\)\s*\{(.*?)\n    \}/s',
            $source,
            $trouvees,
            PREG_SET_ORDER
        );

        $this->assertNotEmpty($trouvees, 'Aucune méthode d\'ouverture trouvée : le test ne vérifie plus rien.');

        foreach ($trouvees as $methode) {
            $this->assertStringContainsString(
                'fermerLesFenetres()',
                $methode[3],
                "La méthode {$methode[1]}() ouvre une fenêtre sans fermer les autres."
            );
        }
    }

    /** Chaque calque porte une clé distincte, sans quoi ils sont confondus au rafraîchissement. */
    public function test_chaque_fenetre_porte_une_cle_distincte(): void
    {
        $rendu = $this->actingAs($this->staff())->get('/dashboard/agents');

        $rendu->assertOk();

        foreach (['fenetre-agent', 'fenetre-fichier', 'fenetre-credit'] as $cle) {
            $rendu->assertSee('wire:key="' . $cle . '"', false);
        }
    }

    /*
     | Aucune fenêtre visible à l'arrivée.
     |
     | Une seule qui garderait sa classe « hidden » manquante s'afficherait
     | par-dessus la page dès l'ouverture de l'écran.
     */
    public function test_aucune_fenetre_n_est_visible_a_l_ouverture(): void
    {
        $html = $this->actingAs($this->staff())->get('/dashboard/agents')->getContent();

        preg_match_all('/<div wire:key="fenetre-[^"]+" class="([^"]*)"/', $html, $calques);

        $this->assertCount(3, $calques[1], 'Les trois fenêtres doivent être présentes.');

        foreach ($calques[1] as $classes) {
            $this->assertStringContainsString('hidden', $classes);
        }
    }

    /** Le bouton de partage se trouve à côté de celui d'ajout. */
    public function test_le_partage_est_a_cote_de_l_ajout(): void
    {
        /*
         | On vise les actions, pas les libellés : l'apostrophe de « Partager
         | l'application » revient échappée dans le HTML, et chercher le texte
         | brut ferait échouer le test pour une raison sans rapport avec l'ordre
         | des boutons.
         */
        $this->actingAs($this->staff())->get('/dashboard/agents')
            ->assertOk()
            ->assertSeeInOrder([
                'wire:click="basculerPartage"',
                'wire:click="openModal"',
            ], false);
    }

    /*
     | Le partage se déplie dans la page.
     |
     | Une quatrième fenêtre empilée au même endroit que les trois autres
     | n'aurait ajouté qu'un risque de confusion de plus.
     */
    public function test_le_partage_n_ouvre_pas_une_quatrieme_fenetre(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('wire:key="partage-application"', $source);
        $this->assertStringNotContainsString(
            'wire:key="fenetre-partage"',
            $source,
            'Le partage doit se déplier dans la page, pas dans un calque.'
        );
    }
}
