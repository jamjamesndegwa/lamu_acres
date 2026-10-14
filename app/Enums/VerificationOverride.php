<?php

namespace App\Enums;

enum VerificationOverride: string
{
    case None = 'none';
    case Flagged = 'flagged';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Flagged => 'Flagged',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }
}
