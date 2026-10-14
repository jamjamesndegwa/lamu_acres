<?php

namespace App\Enums;

enum PaymentReconciliation: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Exception = 'exception';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Exception => 'Exception',
        };
    }
}
