<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Mail;

use App\Domains\Alertes\Models\Alerte;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail d'alerte surestaries/détention (mis en file).
 */
final class AlerteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Alerte $alerte,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Travess — alerte {$this->alerte->type->value}");
    }

    public function content(): Content
    {
        $montant = number_format((float) $this->alerte->montant_menacant, 0, ',', ' ');

        return new Content(htmlString: "<p>Une alerte <strong>{$this->alerte->type->value}</strong> a été déclenchée.</p>"
            ."<p>Montant menaçant : <strong>{$montant} XOF</strong>.</p>"
            .'<p>Connectez-vous à Travess pour traiter le conteneur avant l\'échéance.</p>'
        );
    }
}
