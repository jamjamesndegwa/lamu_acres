<?php

namespace App\Enums;

enum BrokerIntroductionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Accepted => 'Accepted',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
