<?php

namespace App\Enums;

enum SalesOutcome: string
{
    case Cancelled = 'cancelled';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Cancelled => 'Cancelled',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
            self::Expired => 'Expired',
            self::Disputed => 'Disputed',
        };
    }

    public function terminal(): bool
    {
        return $this !== self::Disputed;
    }
}
