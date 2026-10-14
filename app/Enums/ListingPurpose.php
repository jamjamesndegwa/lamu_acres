<?php

namespace App\Enums;

enum ListingPurpose: string
{
    case Sale = 'sale';
    case Lease = 'lease';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::Lease => 'Lease',
        };
    }
}
