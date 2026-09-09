<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Adapters;

use App\Domains\Messagerie\Contracts\ExpediteurMessage;
use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Data\ResultatEnvoi;
use App\Domains\Messagerie\Enums\StatutEnvoi;

/**
 * Expéditeur factice : aucun réseau, déterministe. Enregistre les envois (par
 * empreinte, jamais la PII en clair) pour permettre aux tests d'affirmer qu'un
 * message est parti sur tel canal en portant tel gabarit/paramètres. Lié en
 * singleton pour que le test et le code partagent le même journal.
 */
final class ExpediteurFactice implements ExpediteurMessage
{
    /** @var list<array{canal: string, empreinte: string, gabarit: string, params: array<string, string>}> */
    private array $envois = [];

    public function envoyer(Destinataire $destinataire, MessageSortant $message): ResultatEnvoi
    {
        $this->envois[] = [
            'canal' => $destinataire->canal->value,
            'empreinte' => $destinataire->empreinte(),
            'gabarit' => $message->gabarit,
            'params' => $message->params,
        ];

        return new ResultatEnvoi(StatutEnvoi::EnFile);
    }

    /**
     * @return list<array{canal: string, empreinte: string, gabarit: string, params: array<string, string>}>
     */
    public function envois(): array
    {
        return $this->envois;
    }

    /**
     * @return array{canal: string, empreinte: string, gabarit: string, params: array<string, string>}|null
     */
    public function dernier(): ?array
    {
        return $this->envois === [] ? null : $this->envois[array_key_last($this->envois)];
    }

    public function reset(): void
    {
        $this->envois = [];
    }
}
