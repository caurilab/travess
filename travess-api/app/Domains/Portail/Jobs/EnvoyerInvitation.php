<?php

declare(strict_types=1);

namespace App\Domains\Portail\Jobs;

use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Messagerie\Support\FabriqueExpediteur;
use App\Shared\Jobs\JobTenantScoped;

/**
 * Envoi (en file, principe n°4) du lien d'invitation sur le canal choisi, via
 * l'expéditeur isolé (principe n°9). Le lien signé et le destinataire (PII) ne
 * transitent que dans la charge du job, jamais dans les logs applicatifs.
 */
final class EnvoyerInvitation extends JobTenantScoped
{
    public function __construct(
        string $tenantId,
        public readonly string $canal,
        public readonly string $destinataire,
        public readonly string $lien,
        public readonly string $referenceDossier,
    ) {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        $canal = CanalMessage::from($this->canal);

        app(FabriqueExpediteur::class)->pour($canal)->envoyer(
            new Destinataire($canal, $this->destinataire),
            new MessageSortant('invitation_portail', [
                'lien' => $this->lien,
                'reference' => $this->referenceDossier,
            ]),
        );
    }
}
