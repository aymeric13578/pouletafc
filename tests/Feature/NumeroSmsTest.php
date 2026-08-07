<?php

namespace Tests\Feature;

use App\Fonction\Fonction;
use App\Models\User;
use Tests\TestCase;

/**
 * Résolution du numéro destinataire des SMS (code d'inscription, OTP).
 *
 * L'application client ne demande qu'un numéro WhatsApp et n'envoie jamais
 * "phone" : les comptes se créaient sans numéro joignable, l'envoi du SMS étant
 * conditionné à "phone". L'écran affichait pourtant « Entrez le code reçu par
 * SMS », et le même écart privait ces comptes de récupération de mot de passe.
 *
 * Aucun test n'appelle l'endpoint d'inscription : les identifiants Orange sont
 * codés en dur dans Fonction::getToken(), un appel enverrait un vrai SMS.
 */
class NumeroSmsTest extends TestCase
{
    private function fonction(): Fonction
    {
        return new Fonction();
    }

    public function test_le_numero_whatsapp_sert_quand_le_telephone_est_absent(): void
    {
        $this->assertSame('695427808', $this->fonction()->numeroContact(null, '695427808'));
        $this->assertSame('695427808', $this->fonction()->numeroContact('', '695427808'));
    }

    public function test_le_telephone_reste_prioritaire_quand_il_est_renseigne(): void
    {
        $this->assertSame('652567366', $this->fonction()->numeroContact('652567366', '699439130'));
    }

    public function test_aucun_numero_ne_donne_rien_a_envoyer(): void
    {
        // La chaîne vide fait sauter l'envoi : sans elle on appellerait Orange
        // sur "tel:+237", rejeté sans que l'utilisateur en soit informé.
        $this->assertSame('', $this->fonction()->numeroContact(null, null));
        $this->assertSame('', $this->fonction()->numeroContact('', ''));
    }

    /**
     * Le champ WhatsApp de l'application est un champ texte libre : les clients
     * y écrivent l'indicatif sous toutes ses formes. L'adresse envoyée à Orange
     * étant préfixée "+237", un indicatif déjà présent donnait
     * "tel:+237237690000000", que l'opérateur rejette silencieusement.
     *
     * @dataProvider formatsSaisis
     */
    public function test_l_indicatif_saisi_par_le_client_est_retire(string $saisie): void
    {
        $this->assertSame('695427808', $this->fonction()->numeroLocal($saisie));
    }

    public static function formatsSaisis(): array
    {
        return [
            'sans indicatif' => ['695427808'],
            'avec +237' => ['+237695427808'],
            'avec 237' => ['237695427808'],
            'avec 00237' => ['00237695427808'],
            'avec un 0 initial' => ['0695427808'],
            'avec des espaces' => ['+237 695 42 78 08'],
            'avec des tirets' => ['695-427-808'],
        ];
    }

    public function test_un_numero_local_de_neuf_chiffres_reste_intact(): void
    {
        // Garde-fou du retrait de l'indicatif : il ne doit s'appliquer qu'au-delà
        // de neuf chiffres, sinon un numéro local serait amputé de son début.
        $this->assertSame('237456789', $this->fonction()->numeroLocal('237456789'));
    }

    public function test_les_numeros_deja_en_base_sont_au_format_local(): void
    {
        /*
         * Le repli n'a de sens que si les numéros WhatsApp stockés sont bien des
         * numéros mobiles locaux, comme ceux du champ phone : c'est ce que
         * confirme l'existant.
         */
        $numeros = User::whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->latest('id')
            ->take(20)
            ->pluck('whatsapp');

        if ($numeros->isEmpty()) {
            $this->markTestSkipped('Aucun numéro WhatsApp dans cette base.');
        }

        foreach ($numeros as $numero) {
            $this->assertSame(
                $numero,
                $this->fonction()->numeroLocal($numero),
                "Le numéro déjà stocké {$numero} devrait être inchangé par la normalisation."
            );
        }
    }

    public function test_les_comptes_sans_telephone_restent_joignables(): void
    {
        /*
         * Ces comptes existent : créés depuis l'application client, ils n'ont
         * jamais reçu leur code. Ils doivent désormais être joignables par leur
         * numéro WhatsApp.
         */
        $comptes = User::where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->get();

        if ($comptes->isEmpty()) {
            $this->markTestSkipped('Aucun compte sans téléphone dans cette base.');
        }

        foreach ($comptes as $compte) {
            $this->assertNotSame(
                '',
                $this->fonction()->numeroContact($compte->phone, $compte->whatsapp),
                "Le compte {$compte->id} doit rester joignable par SMS."
            );
        }
    }
}
