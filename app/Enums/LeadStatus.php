<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
    case Nurture = 'nurture';
    case Converted = 'converted';
    case ClosedLost = 'closed_lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Qualified => 'Qualified',
            self::Unqualified => 'Unqualified',
            self::Nurture => 'Nurture',
            self::Converted => 'Converted',
            self::ClosedLost => 'Closed lost',
        };
    }
}
