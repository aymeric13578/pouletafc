<?php

namespace Tests\Feature;

use App\Models\Clando;
use App\Models\Commentaire;
use App\Models\order_detail;
use App\Models\User;
use Tests\TestCase;

/**
 * Commentaires laissés sur une prestation.
 *
 * Distincts des notes : une note se donne une fois par prestation et se referme
 * avec son émoticône. Un commentaire peut venir après coup, se répéter,
 * signaler un problème apparu à la réception — et appeler une réponse.
 */
class CommentairesTest extends TestCase
{
    private array $crees = [];

    private function staff(): User
    {
        $staff = User::where('role', 'admin')->first();

        if (! $staff) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }

        return $staff;
    }

    private function commande(): order_detail
    {
        $commande = order_detail::orderByDesc('id')->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        return $commande;
    }

    protected function tearDown(): void
    {
        Commentaire::whereIn('id', $this->crees)->forceDelete();

        parent::tearDown();
    }

    private function poster(array $charge)
    {
        $reponse = $this->postJson('/api/v1.0/postCommentaire', $charge);

        if ($id = $reponse->json('data.id')) {
            $this->crees[] = $id;
        }

        return $reponse;
    }

    public function test_un_client_peut_commenter_sans_noter(): void
    {
        $commande = $this->commande();

        $this->poster([
            'id_user' => $commande->id_user,
            'id_order' => $commande->id,
            'contenu' => 'Le livreur a été très aimable.',
        ])->assertOk()->assertJsonPath('response', 200);

        $this->assertSame(
            1,
            Commentaire::where('id_order', $commande->id)
                ->where('contenu', 'Le livreur a été très aimable.')
                ->count()
        );
    }

    /*
     | Plusieurs commentaires sur la même prestation.
     |
     | C'est ce qui les distingue d'une note : on peut revenir préciser un
     | détail, ou signaler un problème apparu après coup.
     */
    public function test_une_prestation_accepte_plusieurs_commentaires(): void
    {
        $commande = $this->commande();

        foreach (['Premier mot.', 'Je précise après coup.'] as $texte) {
            $this->poster([
                'id_user' => $commande->id_user,
                'id_order' => $commande->id,
                'contenu' => $texte,
            ])->assertJsonPath('response', 200);
        }

        $this->assertSame(2, Commentaire::whereIn('id', $this->crees)->count());
    }

    /*
     | L'agent est déduit de la prestation, jamais cru sur parole.
     |
     | Le laisser au client permettrait d'attribuer un reproche à n'importe quel
     | agent.
     */
    public function test_l_agent_vise_est_celui_de_la_prestation(): void
    {
        $commande = order_detail::whereNotNull('id_agent')->orderByDesc('id')->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande attribuée.');
        }

        $this->poster([
            'id_user' => $commande->id_user,
            // Agent inventé par le client : il doit être ignoré.
            'id_agent' => 999999,
            'id_order' => $commande->id,
            'contenu' => 'Commentaire sur la livraison.',
        ])->assertJsonPath('response', 200);

        $commentaire = Commentaire::whereIn('id', $this->crees)->latest('id')->firstOrFail();

        $this->assertSame((int) $commande->id_agent, (int) $commentaire->id_agent);
    }

    public function test_commenter_sans_prestation_est_refuse(): void
    {
        $this->postJson('/api/v1.0/postCommentaire', [
            'id_user' => $this->staff()->id,
            'contenu' => 'Un mot sans prestation.',
        ])->assertJsonPath('response', 422);
    }

    public function test_les_commentaires_d_une_prestation_se_lisent(): void
    {
        $commande = $this->commande();

        $this->poster([
            'id_user' => $commande->id_user,
            'id_order' => $commande->id,
            'contenu' => 'Colis remis en bon état.',
        ]);

        $data = $this->getJson('/api/v1.0/getCommentairesPrestation?id_order=' . $commande->id)
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data);
        $this->assertSame('commande', $data[0]['prestation']);
        $this->assertSame('Colis remis en bon état.', $data[0]['contenu']);
    }

    /*
     | Masquer plutôt que supprimer.
     |
     | Effacer ferait disparaître la trace d'un incident que l'exploitation peut
     | avoir besoin de retrouver.
     */
    public function test_un_commentaire_masque_disparait_de_l_application_mais_reste_en_base(): void
    {
        $commande = $this->commande();

        $this->poster([
            'id_user' => $commande->id_user,
            'id_order' => $commande->id,
            'contenu' => 'Commentaire à masquer.',
        ]);

        $commentaire = Commentaire::whereIn('id', $this->crees)->latest('id')->firstOrFail();
        $commentaire->update(['masque' => true]);

        $contenus = collect(
            $this->getJson('/api/v1.0/getCommentairesPrestation?id_order=' . $commande->id)->json('data')
        )->pluck('contenu');

        $this->assertNotContains('Commentaire à masquer.', $contenus);
        $this->assertNotNull(Commentaire::find($commentaire->id), 'La ligne doit rester en base.');
    }

    /** L'agent doit pouvoir lire ce qu'on dit de lui. */
    public function test_un_agent_lit_les_commentaires_le_concernant(): void
    {
        $commande = order_detail::whereNotNull('id_agent')->orderByDesc('id')->first();

        if (! $commande) {
            $this->markTestSkipped('Aucune commande attribuée.');
        }

        $this->poster([
            'id_user' => $commande->id_user,
            'id_order' => $commande->id,
            'contenu' => 'Merci pour la rapidité.',
        ]);

        $data = $this->getJson('/api/v1.0/getCommentairesAgent?id_agent=' . $commande->id_agent)
            ->assertOk()
            ->json('data');

        $this->assertContains('Merci pour la rapidité.', collect($data)->pluck('contenu'));
    }

    public function test_l_ecran_d_administration_liste_et_filtre(): void
    {
        // Répondre et masquer ne se rendent que sur une ligne existante : sans
        // commentaire, le test vérifierait un écran vide.
        $commande = $this->commande();

        $this->poster([
            'id_user' => $commande->id_user,
            'id_order' => $commande->id,
            'contenu' => 'Commentaire affiché au comptoir.',
        ])->assertJsonPath('response', 200);

        $reponse = $this->actingAs($this->staff())->get('/dashboard/commentaires');

        $reponse->assertOk();
        $reponse->assertSeeText('Commentaires');
        $reponse->assertSeeText('Sans réponse');
        $reponse->assertSeeText('Commentaire affiché au comptoir.');

        foreach (['Commande', 'Course', 'Clando'] as $prestation) {
            $reponse->assertSeeText($prestation);
        }

        // Répondre et masquer doivent être offerts.
        $reponse->assertSee('ouvrirReponse', false);
        $reponse->assertSee('basculerMasque', false);
    }

    public function test_l_ecran_est_reserve_a_l_equipe(): void
    {
        $visiteur = User::where('role', 'user')->first();

        if (! $visiteur) {
            $this->markTestSkipped('Aucun compte client en base.');
        }

        $this->actingAs($visiteur)->get('/dashboard/commentaires')->assertForbidden();
    }
}
