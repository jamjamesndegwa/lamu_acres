<?php

namespace App\Enums;

enum OwnershipStructure: string
{
    case Individual = 'individual';
    case Joint = 'joint';
    case Company = 'company';
    case Estate = 'estate';
    case CommunityGroup = 'community_group';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individually owned',
            self::Joint => 'Jointly owned',
            self::Company => 'Company owned',
            self::Estate => 'Estate of a deceased owner',
            self::CommunityGroup => 'Community or group owned',
        };
    }
}
