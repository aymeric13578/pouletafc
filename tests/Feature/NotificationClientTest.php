<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\User;
use App\Support\NotificationClient;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Prévenir un client par tous les canaux disponibles.
 *
 * Le SMS était le seul canal des messages qui comptent — code de confirmation,
 * accusé de commande. Or Orange les accepte, les facture et n'en remet aucun :
 * le client attendait un code qui n'arriverait jamais, devant un écran lui
 * affirmant l'avoir envoyé.
 */
class NotificationClientTest extends TestCase
{
    private function client(): NotificationClient
    {
        return app(NotificationClient::class);
    }

    public function test_le_courriel_part_des_qu_une_adresse_existe(): void
    {
        Mail::fake();

        $utilisateur = new User(['email' => 'client@exemple.test', 'phone' => null, 'whatsapp' => null]);

        $envois = $this->client()->prevenir($utilisateur, 'Objet', 'Votre code est 1234');

        $this->assertTrue($envois['mail']);
        Mail::assertSent(NotificationMail::class);
    }

    /*
     | Le courriel ne dépend pas du canal demandé.
     |
     | C'était auparavant l'un ou l'autre : demander « sms » n'envoyait aucun
     | courriel, et le SMS n'arrivant jamais, le client ne recevait rien.
     */
    public function test_le_courriel_part_meme_quand_un_numero_existe(): void
    {
        Mail::fake();

        $utilisateur = new User([
            'email' => 'client@exemple.test',
            'phone' => '657316683',
            'whatsapp' => null,
        ]);

        $this->assertTrue($this->client()->prevenir($utilisateur, 'Objet', 'Contenu')['mail']);
        Mail::assertSent(NotificationMail::class);
    }

    public function test_une_adresse_absente_ou_invalide_n_empeche_rien(): void
    {
        Mail::fake();

        foreach ([null, '', 'pas-une-adresse'] as $adresse) {
            $envois = $this->client()->prevenir(
                new User(['email' => $adresse, 'phone' => null, 'whatsapp' => null]),
                'Objet',
                'Contenu'
            );

            $this->assertFalse($envois['mail'], "Adresse acceptée à tort : " . var_export($adresse, true));
        }

        Mail::assertNothingSent();
    }

    /*
     | L'application cliente ne demande qu'un numéro WhatsApp et n'envoie jamais
     | « phone » : s'en tenir à ce champ revenait à ne rien envoyer.
     */
    public function test_le_numero_whatsapp_sert_de_repli(): void
    {
        Mail::fake();

        $utilisateur = new User(['email' => null, 'phone' => null, 'whatsapp' => '657316683']);

        // On ne vérifie pas la remise — Orange accepte sans remettre — mais que
        // le canal est bien tenté plutôt qu'ignoré faute de « phone ».
        $envois = $this->client()->prevenir($utilisateur, 'Objet', 'Contenu');

        $this->assertIsBool($envois['sms']);
        $this->assertFalse($envois['mail']);
    }

    public function test_un_utilisateur_absent_ne_fait_rien_echouer(): void
    {
        Mail::fake();

        $this->assertSame(
            ['mail' => false, 'sms' => false],
            $this->client()->prevenir(null, 'Objet', 'Contenu')
        );
    }

    /** L'envoi direct sert à l'inscription, où le compte vient d'être créé. */
    public function test_l_envoi_direct_atteint_l_adresse_fournie(): void
    {
        Mail::fake();

        $envois = $this->client()->prevenirDirectement(
            'nouveau@exemple.test',
            null,
            'NOUVEAU COMPTE POULET AFC',
            'Votre code de confirmation POULET AFC est 4821'
        );

        $this->assertTrue($envois['mail']);
        Mail::assertSent(NotificationMail::class);
    }
}
