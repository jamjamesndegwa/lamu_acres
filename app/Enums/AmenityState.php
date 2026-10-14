<?php

namespace App\Enums;

enum AmenityState: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Unavailable => 'Unavailable',
            self::Unknown => 'Unknown',
            self::Verified => 'Verified',
        };
    }
}
