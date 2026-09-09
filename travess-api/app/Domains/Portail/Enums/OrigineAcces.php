<?php

declare(strict_types=1);

namespace App\Domains\Portail\Enums;

/**
 * Origine d'un accès partagé : invitation émise par le transitaire, ou
 * assignation d'un transitaire par un client autonome.
 */
enum OrigineAcces: string
{
    case InvitationTransitaire = 'invitation_transitaire';
    case AssignationClient = 'assignation_client';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
