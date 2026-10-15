<?php

namespace App\Enums;

enum OwnerRelationship: string
{
    case Owner = 'owner';
    case CoOwner = 'co_owner';
    case Authorized = 'authorized';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::CoOwner => 'Co-owner',
            self::Authorized => 'Authorised representative',
        };
    }

    /**
     * Relationships that let a verified seller see and instruct on a property.
     *
     * @return array<int, self>
     */
    public static function sellerFacing(): array
    {
        return [self::Owner, self::CoOwner, self::Authorized];
    }
}
