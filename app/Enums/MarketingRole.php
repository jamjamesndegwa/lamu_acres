<?php

namespace App\Enums;

enum MarketingRole: string
{
    case Owner = 'owner';
    case AuthorizedMarketer = 'authorized_marketer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::AuthorizedMarketer => 'Authorized marketer',
        };
    }
}
