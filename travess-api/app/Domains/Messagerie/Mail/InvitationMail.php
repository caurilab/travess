<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail d'invitation au portail (ADR-013, 7.2b). Ne porte que des primitives ;
 * envoyé SYNCHRONEMENT depuis le job EnvoyerInvitation (déjà la frontière async
 * + chiffré) — jamais re-mis en file, pour ne pas sérialiser le lien secret.
 */
final class InvitationMail extends Mailable
{
    public function __construct(
        public readonly string $lien,
        public readonly string $reference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Travess — accès à votre dossier '.$this->reference);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Bonjour,</p>'
            .'<p>Un transitaire vous partage le suivi du dossier <strong>'.e($this->reference).'</strong> sur Travess.</p>'
            .'<p><a href="'.e($this->lien).'">Ouvrir mon dossier</a></p>'
            .'<p>Ce lien est personnel et à usage unique. Il expire prochainement.</p>'
        );
    }
}
