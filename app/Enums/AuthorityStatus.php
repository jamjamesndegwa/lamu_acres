<?php

namespace App\Enums;

enum AuthorityStatus: string
{
    case Submitted = 'submitted';
    case Active = 'active';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Active => 'Active',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
