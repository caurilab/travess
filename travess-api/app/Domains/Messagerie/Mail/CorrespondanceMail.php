<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail de correspondance libre (demande adressée à l'armateur).
 *
 * Ne porte que des primitives (objet + corps composés par l'agent). Le corps
 * est du texte : il est échappé puis les retours à la ligne convertis en <br>.
 */
final class CorrespondanceMail extends Mailable
{
    public function __construct(
        public readonly string $objet,
        public readonly string $corps,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->objet);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<div>'.nl2br(e($this->corps)).'</div>');
    }
}
