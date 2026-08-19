<?php

namespace Tests\Feature;

use App\Models\order_detail;
use App\Support\AnnulationDeCommande;
use Tests\TestCase;

/**
 * Une annulation doit dire pourquoi, et une commande annulée ne doit plus partir
 * en livraison.
 *
 * Ces cas n'écrivent pas en base : la configuration de test pointe sur la base
 * de développement, qu'un rafraîchissement viderait. Les règles sont éprouvées
 * sur des objets en mémoire, et les refus sur des identifiants inexistants — la
 * validation s'exécutant avant toute lecture.
 */
class AnnulationMotiveeTest extends TestCase
{
    public function test_un_motif_vide_ou_trop_court_est_refuse(): void
    {
        $this->assertFalse(AnnulationDeCommande::motifValide(null));
        $this->assertFalse(AnnulationDeCommande::motifValide(''));
        $this->assertFalse(AnnulationDeCommande::motifValide('  '));
        $this->assertFalse(AnnulationDeCommande::motifValide('ok'));

        $this->assertTrue(AnnulationDeCommande::motifValide('Client injoignable'));
    }

    public function test_le_motif_est_nettoye_sans_etre_denature(): void
    {
        $this->assertSame(
            'Client injoignable au téléphone',
            AnnulationDeCommande::nettoyerLeMotif("  Client   injoignable\n au téléphone  ")
        );

        // La borne protège la colonne, qui est un varchar(255).
        $this->assertSame(
            AnnulationDeCommande::MOTIF_MAXIMUM,
            mb_strlen(AnnulationDeCommande::nettoyerLeMotif(str_repeat('a', 400)))
        );
    }

    public function test_une_commande_annulee_n_est_plus_prenable(): void
    {
        $annulee = new order_detail(['status' => AnnulationDeCommande::STATUT]);
        $attente = new order_detail(['status' => 'want']);
        $prise = new order_detail(['status' => 'want']);
        $prise->id_agent = 7;

        $this->assertTrue(AnnulationDeCommande::estAnnulee($annulee));
        $this->assertFalse(AnnulationDeCommande::encorePrenable($annulee));

        // Une commande déjà prise n'est pas annulée pour autant : l'agent qui
        // arrive trop tard ne doit pas lire « annulée ».
        $this->assertFalse(AnnulationDeCommande::estAnnulee($prise));
        $this->assertFalse(AnnulationDeCommande::encorePrenable($prise));

        $this->assertTrue(AnnulationDeCommande::encorePrenable($attente));
    }

    public function test_le_mur_refuse_une_annulation_sans_motif(): void
    {
        // Identifiant inexistant à dessein : la validation s'exécute avant la
        // lecture, le refus se constate donc sans toucher à une vraie commande.
        $reponse = $this->postJson('/commandes/999999999/statut', ['status' => 'failed']);

        $reponse->assertStatus(422);
        $reponse->assertJsonValidationErrors('reason');
    }

    public function test_le_mur_accepte_les_autres_statuts_sans_motif(): void
    {
        // Sans motif exigé, on passe la validation et on échoue sur la commande
        // introuvable : c'est bien que « reason » n'était pas requis.
        $this->postJson('/commandes/999999999/statut', ['status' => 'process'])
            ->assertStatus(404);
    }

    public function test_la_carte_clando_refuse_une_annulation_sans_motif(): void
    {
        $this->postJson('/clando/999999999/annulation', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_l_application_apprend_qu_une_commande_a_disparu(): void
    {
        $reponse = $this->getJson('/api/v1.0/disponibiliteCommande?type=order&id=999999999');

        $reponse->assertOk();
        $reponse->assertJsonPath('disponible', false);
    }

    public function test_l_api_refuse_une_annulation_sans_motif(): void
    {
        $this->postJson('/api/v1.0/annulerCommande', ['type' => 'order', 'id' => 1])
            ->assertOk()
            ->assertJsonPath('response', 400);
    }

    public function test_les_motifs_proposes_sont_servis_aux_applications(): void
    {
        $reponse = $this->getJson('/api/v1.0/motifsAnnulation');

        $reponse->assertOk();
        $reponse->assertJsonPath('response', 200);
        $this->assertNotEmpty($reponse->json('data'));
    }
}
