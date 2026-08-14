<?php

namespace Tests\Feature;

use App\Fonction\Fonction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Création de compte depuis l'application.
 *
 * Deux défauts s'y cumulaient. L'application n'a qu'un champ de numéro, intitulé
 * WhatsApp, et n'envoie donc jamais « phone » : la colonne restait vide sur tous
 * les comptes créés depuis le mobile — impossible d'appeler un client. Et rien
 * n'exigeait ni numéro ni adresse e-mail, si bien qu'un compte pouvait naître
 * sans aucun moyen de joindre son titulaire.
 */
class InscriptionTest extends TestCase
{
    private const URL = '/api/v1.0/register';

    /** Numéro qui n'existe pas encore, pour ne pas heurter le contrôle d'unicité. */
    private function numeroLibre(): string
    {
        for ($i = 0; $i < 50; $i++) {
            $numero = '69' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

            if (! User::where('phone', $numero)->orWhere('whatsapp', $numero)->exists()) {
                return $numero;
            }
        }

        $this->markTestSkipped('Impossible de tirer un numéro libre.');
    }

    private function supprimer(?string $numero, ?string $email): void
    {
        if ($numero) {
            DB::table('users')->where('phone', $numero)->orWhere('whatsapp', $numero)->delete();
        }
        if ($email) {
            DB::table('users')->where('email', $email)->delete();
        }
    }

    public function test_le_numero_whatsapp_est_aussi_enregistre_comme_telephone(): void
    {
        /*
         * Le cœur du problème signalé : des comptes créés dont le numéro ne se
         * stockait pas. L'application n'envoie que « whatsapp ».
         */
        $numero = $this->numeroLibre();
        $email = 'test-inscription-' . $numero . '@exemple.test';

        $reponse = $this->postJson(self::URL, [
            'lastname' => 'Essai',
            'password' => '1234',
            'confirmpassword' => '1234',
            'city' => 'Garoua',
            'whatsapp' => $numero,
            'email' => $email,
        ]);

        $reponse->assertOk()->assertJson(['response' => 200]);

        $compte = User::where('whatsapp', $numero)->first();

        $this->assertNotNull($compte, 'Le compte doit être créé.');
        $this->assertSame($numero, $compte->phone, 'Le numéro doit aussi remplir la colonne phone.');
        $this->assertSame($numero, $compte->whatsapp);

        $this->supprimer($numero, $email);
    }

    public function test_le_numero_est_obligatoire(): void
    {
        $email = 'test-sans-numero-' . random_int(1000, 9999) . '@exemple.test';

        $this->postJson(self::URL, [
            'lastname' => 'Essai',
            'password' => '1234',
            'confirmpassword' => '1234',
            'city' => 'Garoua',
            'email' => $email,
        ])->assertOk()->assertJson(['response' => 400]);

        $this->assertNull(User::where('email', $email)->first(), 'Aucun compte ne doit naître sans numéro.');
    }

    public function test_l_adresse_email_est_obligatoire(): void
    {
        $numero = $this->numeroLibre();

        $this->postJson(self::URL, [
            'lastname' => 'Essai',
            'password' => '1234',
            'confirmpassword' => '1234',
            'city' => 'Garoua',
            'whatsapp' => $numero,
        ])->assertOk()->assertJson(['response' => 400]);

        $this->assertNull(User::where('whatsapp', $numero)->first());

        $this->supprimer($numero, null);
    }

    public function test_un_numero_deja_pris_est_refuse(): void
    {
        /*
         * Sans ce contrôle, deux comptes portaient le même numéro et le code de
         * confirmation partait vers celui qu'on ne voulait pas.
         */
        $existant = User::whereNotNull('whatsapp')->where('whatsapp', '!=', '')->firstOrFail();
        $email = 'test-doublon-' . random_int(1000, 9999) . '@exemple.test';

        $this->postJson(self::URL, [
            'lastname' => 'Essai',
            'password' => '1234',
            'confirmpassword' => '1234',
            'city' => 'Garoua',
            'whatsapp' => $existant->whatsapp,
            'email' => $email,
        ])->assertOk()->assertJson(['response' => 400]);

        $this->assertNull(User::where('email', $email)->first());
    }

    public function test_les_mots_de_passe_doivent_concorder(): void
    {
        $numero = $this->numeroLibre();

        $this->postJson(self::URL, [
            'lastname' => 'Essai',
            'password' => '1234',
            'confirmpassword' => '9999',
            'city' => 'Garoua',
            'whatsapp' => $numero,
            'email' => 'test-' . $numero . '@exemple.test',
        ])->assertOk()->assertJson(['response' => 400]);

        $this->supprimer($numero, null);
    }

    public function test_un_numero_saisi_avec_son_indicatif_reste_joignable(): void
    {
        /*
         * L'envoi préfixe « +237 » au contact. Un numéro déjà saisi avec son
         * indicatif produisait « +237+237690… », que l'opérateur ne remet à
         * personne.
         */
        $fonction = new Fonction();

        foreach (['690123456', '+237690123456', '237690123456', '+237 690 12 34 56'] as $saisie) {
            $this->assertSame('690123456', $fonction->numeroLocal($saisie), "Saisie : $saisie");
        }
    }

    public function test_la_configuration_de_courrier_ne_pointe_plus_sur_l_outil_de_developpement(): void
    {
        /*
         * Le .env du serveur porte les valeurs d'exemple de Laravel : mailpit
         * sur le port 1025, qui n'existe pas en production. Tout envoi échouait
         * en silence, l'inscription attrapant l'exception et la journalisant.
         */
        $transport = config('mail.default');

        // Ce qui compte est le transport réellement retenu, pas la configuration
        // du transport smtp, qui peut rester en place sans être utilisée.
        if ($transport === 'smtp') {
            $this->assertNotContains(
                config('mail.mailers.smtp.host'),
                ['mailpit', 'localhost', '127.0.0.1'],
                'Le transport retenu pointe sur un serveur de développement.',
            );
        }

        $this->assertNotSame(
            'hello@example.com',
            config('mail.from.address'),
            'Un expéditeur en example.com est refusé ou classé indésirable par les receveurs.',
        );
    }
}
