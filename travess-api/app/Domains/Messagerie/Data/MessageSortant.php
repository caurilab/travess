<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Data;

/**
 * Message sortant décrit par un gabarit + ses paramètres (prépare les templates
 * WhatsApp validés sans coupler le métier au fournisseur).
 */
final class MessageSortant
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public readonly string $gabarit,
        public readonly array $params = [],
    ) {}
}
