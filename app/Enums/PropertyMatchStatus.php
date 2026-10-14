<?php

namespace App\Enums;

enum PropertyMatchStatus: string
{
    case Shortlisted = 'shortlisted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Shortlisted => 'Shortlisted',
            self::Rejected => 'Rejected',
        };
    }
}
