<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Earned = 'earned';
    case Payable = 'payable';
    case Paid = 'paid';
    case Disputed = 'disputed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Accepted => 'Accepted',
            self::Earned => 'Earned',
            self::Payable => 'Payable',
            self::Paid => 'Paid',
            self::Disputed => 'Disputed',
            self::Superseded => 'Superseded',
        };
    }
}
