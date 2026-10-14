<?php

namespace App\Enums;

enum BrokerIntroductionStatus: string
{
    case Submitted = 'submitted';
    case Review = 'review';
    case Disputed = 'disputed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Active = 'active';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Review => 'Review',
            self::Disputed => 'Disputed',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function protectsCommission(): bool
    {
        return $this === self::Accepted || $this === self::Active;
    }
}
