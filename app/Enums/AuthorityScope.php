<?php

namespace App\Enums;

enum AuthorityScope: string
{
    case Sale = 'sale';
    case Lease = 'lease';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale only',
            self::Lease => 'Lease only',
            self::Both => 'Sale and lease',
        };
    }

    public function covers(ListingPurpose $purpose): bool
    {
        return $this === self::Both || $this->value === $purpose->value;
    }
}
