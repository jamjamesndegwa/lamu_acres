<?php

namespace App\Enums;

enum MatchBand: string
{
    case Exact = 'exact';
    case Near = 'near';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact match',
            self::Near => 'Near match',
        };
    }
}
