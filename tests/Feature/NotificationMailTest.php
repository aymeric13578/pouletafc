<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use Tests\TestCase;

/**
 * Le message partait en HTML seul, sans structure de document, et appelait son
 * logo sur pouletafc.2gether-network.com — un domaine qui ne résout plus. Trois
 * signaux que les filtres additionnent, et les messages tombaient en
 * indésirables.
 *
 * Ce qui est vérifié ici est ce qu'un filtre regarde, pas l'apparence.
 */
class NotificationMailTest extends TestCase
{
    private function messageRendu(): string
    {
        $mailable = new NotificationMail(
            'Poulet AFC - commande CMD-4821',
            'Bonjour,<br>Votre commande <b>CMD-4821</b> a été prise en charge.',
            'Commande prise en charge'
        );

        return $mailable->render();
    }

    public function test_le_message_est_un_document_html_complet(): void
    {
        $rendu = $this->messageRendu();

        $this->assertStringContainsString('<!DOCTYPE html>', $rendu);
        $this->assertStringContainsString('</html>', $rendu);
        $this->assertStringContainsString('charset="utf-8"', $rendu);
    }

    public function test_aucun_appel_vers_le_domaine_disparu(): void
    {
        $this->assertStringNotContainsString('2gether-network', $this->messageRendu());
    }

    public function test_le_logo_est_servi_par_le_domaine_courant(): void
    {
        $this->assertStringContainsString('https://pouletafc.com/logo_blue.png', $this->messageRendu());
    }

    public function test_une_version_texte_accompagne_le_html(): void
    {
        $mailable = new NotificationMail(
            'Poulet AFC - commande CMD-4821',
            'Bonjour,<br>Votre commande <b>CMD-4821</b> a été prise en charge.<br>Montant : 7 500 F.',
            'Commande prise en charge'
        );

        $contenu = $mailable->content();

        $this->assertSame('mail.notification-texte', $contenu->text);

        // Le balisage devient du texte lisible, et les <br> des sauts de ligne
        // réels — sans quoi tout le message tiendrait sur une seule ligne.
        $texte = $contenu->with['texte'];

        $this->assertStringNotContainsString('<br>', $texte);
        $this->assertStringNotContainsString('<b>', $texte);
        $this->assertStringContainsString("Bonjour,\nVotre commande CMD-4821", $texte);
        $this->assertStringContainsString('Montant : 7 500 F.', $texte);
    }

    public function test_une_adresse_de_reponse_du_domaine_est_posee(): void
    {
        $enveloppe = (new NotificationMail('objet', 'contenu', 'titre'))->envelope();

        $this->assertSame('infos@pouletafc.com', $enveloppe->replyTo[0]->address);
    }
}
