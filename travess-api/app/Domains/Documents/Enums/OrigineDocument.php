<?php

declare(strict_types=1);

namespace App\Domains\Documents\Enums;

/**
 * Canal par lequel un document est entré dans le système.
 */
enum OrigineDocument: string
{
    case UploadWeb = 'upload_web';
    case PhotoMobile = 'photo_mobile';
    case Whatsapp = 'whatsapp';
    case Scan = 'scan';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
