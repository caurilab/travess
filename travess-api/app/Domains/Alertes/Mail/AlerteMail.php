<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail d'alerte surestaries/détention (mis en file).
 *
 * Ne porte que des primitives (pas de modèle Eloquent) : la mailable est un job
 * distinct, désérialisé par un worker HORS contexte tenant ; sérialiser une
 * Alerte (scopée + RLS) échouerait à la restauration. Cf. ADR-004/011.
 */
final class AlerteMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $typeAlerte,
        public readonly int $montantMenacant,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Travess — alerte {$this->typeAlerte}");
    }

    public function content(): Content
    {
        $montant = number_format($this->montantMenacant, 0, ',', ' ');

        return new Content(htmlString: "<p>Une alerte <strong>{$this->typeAlerte}</strong> a été déclenchée.</p>"
            ."<p>Montant menaçant : <strong>{$montant} XOF</strong>.</p>"
            .'<p>Connectez-vous à Travess pour traiter le conteneur avant l\'échéance.</p>'
        );
    }
}
