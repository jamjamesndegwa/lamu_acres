<?php

namespace App\Enums;

enum OwnerLinkStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verified => 'Verified',
            self::Revoked => 'Revoked',
        };
    }
}
