<?php

namespace App\Enums;

enum QualifyingEvent: string
{
    case SaleCompleted = 'sale_completed';
    case LeaseConfirmed = 'lease_confirmed';

    public function label(): string
    {
        return match ($this) {
            self::SaleCompleted => 'Successful sale completion',
            self::LeaseConfirmed => 'Verified lease confirmation',
        };
    }
}
