<?php

namespace Tests\Feature;

use App\Fonction\Fonction;
use Tests\TestCase;

/**
 * Composition de la demande d'envoi Orange.
 *
 * Les SMS ne partaient pas : Orange accepte l'envoi, débite le forfait, et ne
 * remet rien. Le blocage est en aval de l'API — ni le format du numéro, ni
 * l'adresse d'émission n'y changent quoi que ce soit, Orange acceptant à
 * l'identique le remplissage, un numéro réel ou un numéro inventé.
 *
 * Ce qui se vérifie ici est donc la constance de la demande : la même requête
 * qu'avant l'extraction des identifiants, sans rien y avoir ajouté.
 */
class EnvoiSmsTest extends TestCase
{
    private function demande(string $numero = '657316683'): array
    {
        return (new Fonction())->corpsDeLEnvoi('Votre code est 1234', $numero)['outboundSMSMessageRequest'];
    }

    /*
     | Aucun nom d'expéditeur, jamais.
     |
     | Orange refuse tout nom non enregistré sur le contrat, et en déclarer un
     | ferait échouer entièrement des envois aujourd'hui acceptés. Ce test est
     | une garde : il tombera si quelqu'un le réintroduit sans y avoir réfléchi.
     */
    public function test_aucun_nom_d_expediteur_n_est_jamais_declare(): void
    {
        $this->assertArrayNotHasKey('senderName', $this->demande());
    }

    /*
     | Le numéro est normalisé avant l'indicatif.
     |
     | Un numéro déjà saisi avec « 237 » produisait « +237237690… », que
     | l'opérateur ne remet à personne.
     */
    public function test_l_indicatif_n_est_jamais_doublé(): void
    {
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
