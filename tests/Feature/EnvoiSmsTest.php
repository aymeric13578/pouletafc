<?php

namespace Tests\Feature;

use App\Fonction\Fonction;
use Tests\TestCase;

/**
 * Composition de la demande d'envoi Orange.
 *
 * Les SMS ne partaient pas : Orange accepte l'envoi, débite le forfait, mais ne
 * remet rien tant qu'aucun nom d'expéditeur n'est enregistré sur le contrat.
 * Le jour où il le sera, il suffira de renseigner une variable — encore
 * faut-il que le code sache la transmettre, et surtout qu'il s'en passe d'ici
 * là.
 */
class EnvoiSmsTest extends TestCase
{
    private function demande(string $numero = '657316683'): array
    {
        return (new Fonction())->corpsDeLEnvoi('Votre code est 1234', $numero)['outboundSMSMessageRequest'];
    }

    /*
     | Sans nom enregistré, on n'en déclare aucun.
     |
     | Orange refuse tout nom non autorisé et l'envoi échoue entièrement : le
     | poser d'avance remplacerait des messages non remis par des messages
     | refusés, en cassant aussi le parcours d'inscription qui attend la
     | réponse.
     */
    public function test_aucun_nom_d_expediteur_n_est_declare_par_defaut(): void
    {
        config(['orange_sms.sender_name' => null]);

        $this->assertArrayNotHasKey('senderName', $this->demande());
    }

    public function test_un_nom_vide_ou_en_espaces_ne_compte_pas(): void
    {
        config(['orange_sms.sender_name' => '   ']);

        $this->assertArrayNotHasKey('senderName', $this->demande());
    }

    /** Le jour où Orange l'enregistre, une variable suffit. */
    public function test_le_nom_est_transmis_des_qu_il_est_renseigne(): void
    {
        config(['orange_sms.sender_name' => 'POULETAFC']);

        $this->assertSame('POULETAFC', $this->demande()['senderName']);
    }

    /*
     | Le numéro est normalisé avant l'indicatif.
     |
     | Un numéro déjà saisi avec « 237 » produisait « +237237690… », que
     | l'opérateur ne remet à personne.
     */
    public function test_l_indicatif_n_est_jamais_doublé(): void
    {
        config(['orange_sms.sender_name' => null]);

        foreach (['657316683', '237657316683', '+237 657 31 66 83'] as $saisie) {
            $this->assertSame(
                'tel:+237657316683',
                $this->demande($saisie)['address'],
                "Numéro mal normalisé : $saisie"
            );
        }
    }

    public function test_l_adresse_d_emission_vient_de_la_configuration(): void
    {
        config(['orange_sms.sender_address' => 'tel:+2370000000']);

        $this->assertSame('tel:+2370000000', $this->demande()['senderAddress']);
    }

    /** Le message voyage tel quel, accents compris. */
    public function test_le_message_est_transmis_intact(): void
    {
        $demande = (new Fonction())->corpsDeLEnvoi('Créé à Garoua — 4821', '657316683');

        $this->assertSame(
            'Créé à Garoua — 4821',
            $demande['outboundSMSMessageRequest']['outboundSMSTextMessage']['message']
        );
    }

    /*
     | Les identifiants sortent du code sans changer de valeur.
     |
     | Le défaut de configuration reprend ce qui était écrit en dur : une
     | production sans variable d'environnement doit continuer à fonctionner à
     | l'identique.
     */
    public function test_les_identifiants_restent_ceux_qui_fonctionnaient(): void
    {
        $this->assertStringStartsWith('Basic ', config('orange_sms.authorization'));
        $this->assertSame('https://api.orange.com/oauth/v3/token', config('orange_sms.token_url'));
        $this->assertSame('tel:+2370000000', config('orange_sms.sender_address'));
    }
}
