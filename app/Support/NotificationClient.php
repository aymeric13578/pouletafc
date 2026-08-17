<?php

namespace App\Support;

use App\Fonction\Fonction;
use App\Mail\NotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Prévenir un client, par tous les moyens dont on dispose.
 *
 * Le SMS était le seul canal des messages qui comptent — code de confirmation,
 * accusé de commande. Or Orange les accepte, les facture, et n'en remet aucun :
 * un client qui s'inscrit attend donc un code qui n'arrivera jamais, devant un
 * écran qui lui affirme l'avoir envoyé.
 *
 * Le courriel, lui, part et arrive. Il devient le canal principal, le SMS
 * restant tenté en complément — le jour où Orange débloquera, rien ne sera à
 * remettre en place.
 *
 * Les deux envois sont indépendants : l'échec de l'un ne doit jamais empêcher
 * l'autre, ni faire échouer l'inscription elle-même. Un client créé mais non
 * prévenu vaut mieux qu'un client non créé.
 */
class NotificationClient
{
    /**
     * Envoie un message à un utilisateur sur tous ses canaux connus.
     *
     * @return array{mail: bool, sms: bool} ce qui est effectivement parti
     */
    public function prevenir(?User $utilisateur, string $objet, string $contenu, ?string $titre = null): array
    {
        if (! $utilisateur) {
            return ['mail' => false, 'sms' => false];
        }

        return [
            'mail' => $this->parCourriel($utilisateur->email, $objet, $contenu, $titre ?? $objet),
            /*
             | L'application cliente ne demande qu'un numéro WhatsApp et
             | n'envoie jamais « phone » : s'en tenir à ce champ revenait à ne
             | rien envoyer du tout.
             */
            'sms' => $this->parSms($utilisateur->phone ?: $utilisateur->whatsapp, $contenu),
        ];
    }

    /**
     * Envoie à une adresse et à un numéro donnés, sans passer par un compte.
     *
     * Sert aux envois d'inscription, où l'utilisateur vient d'être créé et où
     * l'on tient déjà ses coordonnées sous la main.
     *
     * @return array{mail: bool, sms: bool}
     */
    public function prevenirDirectement(?string $email, ?string $numero, string $objet, string $contenu, ?string $titre = null): array
    {
        return [
            'mail' => $this->parCourriel($email, $objet, $contenu, $titre ?? $objet),
            'sms' => $this->parSms($numero, $contenu),
        ];
    }

    private function parCourriel(?string $email, string $objet, string $contenu, string $titre): bool
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            Mail::to($email)->send(new NotificationMail($objet, $contenu, $titre));

            return true;
        } catch (\Throwable $e) {
            Log::error('Notification client — échec du courriel', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function parSms(?string $numero, string $contenu): bool
    {
        if (! $numero) {
            return false;
        }

        try {
            $reponse = (new Fonction())->sendSms($contenu, $numero);

            /*
             | « Parti » ne veut pas dire « reçu ».
             |
             | Orange répond 201 avec un resourceURL sur des messages qu'il ne
             | remet pas. On ne peut donc rien affirmer de plus que l'acceptation
             | — et c'est précisément pourquoi le courriel ne doit pas dépendre
             | de ce résultat.
             */
            return isset($reponse['outboundSMSMessageRequest']);
        } catch (\Throwable $e) {
            Log::error('Notification client — échec du SMS', [
                'numero' => $numero,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
