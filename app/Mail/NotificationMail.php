<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;
    public $object;
    public $content;
    public $title;

    /**
     * Create a new message instance.
     */
    public function __construct($object,$content,$title)
    {
        $this->object = $object;
        $this->content = $content;
        $this->title = $title;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject:  $this->object,
            /*
            | Une adresse de réponse valide, sur le domaine expéditeur. Sans
            | elle, répondre à un accusé de commande n'aboutissait nulle part —
            | et l'absence de Reply-To sur un message commercial fait partie de
            | ce qu'un filtre relève.
            */
            replyTo: [new Address('infos@pouletafc.com', 'Poulet AFC')],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            text: 'mail.notification-texte',
            with: [
                'title' => $this->title,
                'content' => $this->content,
                'texte' => $this->enTexteBrut(),
            ],
        );
    }

    /**
     * Le corps du message débarrassé de son balisage, pour la version texte.
     *
     * Le contenu est parfois du HTML — les appelants y glissent des <br> pour
     * séparer les lignes. On les rend en sauts de ligne réels plutôt que de les
     * effacer, sinon tout le message se retrouve sur une seule ligne.
     */
    private function enTexteBrut(): string
    {
        $texte = preg_replace('#<br\s*/?>#i', "\n", (string) $this->content);
        $texte = preg_replace('#</p\s*>#i', "\n\n", $texte);

        return trim(html_entity_decode(strip_tags($texte), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
