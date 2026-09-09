<?php

declare(strict_types=1);

namespace App\Domains\Portail\Jobs;

use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Messagerie\Support\FabriqueExpediteur;
use App\Shared\Jobs\JobTenantScoped;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;

/**
 * Envoi (en file, principe n°4) du lien d'invitation sur le canal choisi, via
 * l'expéditeur isolé (principe n°9).
 *
 * Charge CHIFFRÉE (ShouldBeEncrypted, audit 7.2a M2) : le lien porte le token
 * secret et le destinataire est une PII — ni l'un ni l'autre ne doit apparaître
 * en clair dans la file ni dans `failed_jobs`.
 */
final class EnvoyerInvitation extends JobTenantScoped implements ShouldBeEncrypted
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
