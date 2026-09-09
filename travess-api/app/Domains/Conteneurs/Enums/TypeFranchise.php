<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Enums;

/**
 * Nature d'une franchise : surestaries (demurrage) ou détention.
 */
enum TypeFranchise: string
{
    case Surestaries = 'surestaries';
    case Detention = 'detention';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
