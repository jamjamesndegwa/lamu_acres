<?php

namespace App\Enums;

enum DocumentAccessStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }
}
