<?php

namespace App\Enums;

enum DealKind: string
{
    case Sale = 'sale';
    case Lease = 'lease';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Outright sale',
            self::Lease => 'Lease',
        };
    }
}
