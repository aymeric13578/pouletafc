<?php

namespace Tests\Feature;

use App\Models\order_detail;
use App\Models\User;
use Tests\TestCase;

/**
 * Modification d'une commande telle que le navigateur la déclenche.
 *
 * Les pages Folio ne sont pas pilotables par Volt::test : on passe donc par la
 * route que Livewire appelle réellement, avec l'instantané rendu par la page.
 * C'est le seul moyen de reproduire un clic — et le 500 qu'il provoquait.
 */
class ModificationCommandeLivewireTest extends TestCase
{
    private function admin(): User
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        return $admin;
    }

    /**
     * Extrait l'instantané Livewire du premier composant de la page.
     */
    private function instantane(string $html): array
    {
        if (! preg_match('/wire:snapshot="([^"]+)"/', $html, $m)) {
            $this->fail('Aucun composant Livewire trouvé dans la page.');
        }

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    }

    private function appeler(array $snapshot, string $methode, array $params = [])
    {
        return $this->postJson('/livewire/update', [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => (object) [],
                'calls' => [['path' => '', 'method' => $methode, 'params' => $params]],
            ]],
        ]);
    }

    /**
     * Exerce chaque action de l'écran et signale celles qui échouent.
     *
     * Écrit comme un balayage plutôt qu'un cas : le rapport disait « erreur 500
     * en modifiant une commande » sans dire quel bouton, et deviner aurait pris
     * plus de temps que de tout essayer.
     */
    public function test_aucune_action_de_l_ecran_ne_leve_d_erreur(): void
    {
        $commande = order_detail::whereNotNull('id_cart')
            ->whereNotIn('status', ['Success', 'failed'])
            ->orderByDesc('id')
            ->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande modifiable en base.');
        }

        $this->actingAs($this->admin());

        $html = $this->get('/dashboard/commandes')->assertOk()->getContent();
        $snapshot = $this->instantane($html);

        $gestes = [
            ['openDetailsModal', [$commande->id]],
            ['closeDetailsModal', []],
            ['openModal', [$commande->id]],
            ['closeModal', []],
            ['ouvrirPanier', [$commande->id]],
            ['ajouterProduit', []],
            ['changerQuantite', [999999, 1]],
            ['retirerLigne', [999999]],
            ['togglePaiement', [$commande->id]],
        ];

        $echecs = [];

        foreach ($gestes as [$methode, $params]) {
            $reponse = $this->appeler($snapshot, $methode, $params);

            if ($reponse->getStatusCode() >= 500) {
                $echecs[] = $methode . ' → ' . $reponse->getStatusCode();

                continue;
            }

            // On repart de l'état renvoyé : les gestes s'enchaînent comme au clic.
            if ($nouveau = $reponse->json('components.0.snapshot')) {
                $snapshot = json_decode($nouveau, true);
            }
        }

        // Remise en l'état du paiement, basculé ci-dessus.
        $this->appeler($snapshot, 'togglePaiement', [$commande->id]);

        $this->assertSame([], $echecs, 'Actions en échec : ' . implode(', ', $echecs));
    }

    public function test_ouvrir_puis_modifier_un_panier_ne_leve_pas_d_erreur(): void
    {
        /*
         | Une commande garnie ET présente sur la première page.
         |
         | L'écran en affiche dix par page : une commande plus ancienne ne serait
         | pas rendue, et l'absence des boutons ne prouverait rien sur le code.
         */
        $premiereePage = order_detail::orderByDesc('id')->limit(10)->pluck('id');

        $commande = order_detail::whereIn('id', $premiereePage)
            ->whereNotNull('id_cart')
            ->whereNotIn('status', ['Success', 'failed'])
            ->whereIn('id_cart', \App\Models\CartItem::select('cart_id'))
            ->orderByDesc('id')
            ->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande garnie et modifiable sur la première page.');
        }

        $this->actingAs($this->admin());

        $html = $this->get('/dashboard/commandes')->assertOk()->getContent();
        $snapshot = $this->instantane($html);

        // Ouvrir le panier de la commande.
        $reponse = $this->appeler($snapshot, 'ouvrirPanier', [$commande->id]);
        $reponse->assertOk();

        $apres = json_decode($reponse->json('components.0.snapshot'), true);

        $this->assertSame(
            $commande->id,
            $apres['data']['panierOuvert'] ?? null,
            'Le panier ouvert doit être celui demandé.'
        );

        // Le rendu renvoyé doit contenir le détail du panier.
        $this->assertStringContainsString('changerQuantite', $reponse->json('components.0.effects.html') ?? '');
    }
}
