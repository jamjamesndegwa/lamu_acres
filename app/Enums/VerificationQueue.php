<?php

namespace App\Enums;

enum VerificationQueue: string
{
    case New = 'new';
    case Missing = 'missing';
    case Exceptions = 'exceptions';
    case Expiring = 'expiring';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New Reviews',
            self::Missing => 'Missing Documents',
            self::Exceptions => 'Verification Exceptions',
            self::Expiring => 'Expiring Checks',
            self::Completed => 'Completed Reviews',
        };
    }
}
