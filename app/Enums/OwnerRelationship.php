<?php

namespace App\Enums;

enum OwnerRelationship: string
{
    case Owner = 'owner';
    case Authorized = 'authorized';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Authorized => 'Authorised representative',
        };
    }
}
