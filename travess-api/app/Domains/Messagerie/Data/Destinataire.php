<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Data;

use App\Domains\Messagerie\Enums\CanalMessage;

/**
 * Destinataire d'un message sortant : une PII brute (numéro E.164 ou email),
 * car le destinataire n'a pas encore de compte. Ne jamais journaliser `adresse`
 * en clair (log par empreinte).
 */
final class Destinataire
{
    public function __construct(
        public readonly CanalMessage $canal,
        public readonly string $adresse,
        public readonly ?string $langue = null,
    ) {}

    /**
     * Empreinte non réversible pour les logs (jamais l'adresse en clair).
     */
    public function empreinte(): string
    {
        return substr(hash('sha256', $this->adresse), 0, 12);
    }
}
