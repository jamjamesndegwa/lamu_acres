<?php

namespace App\Enums;

enum RoadAccess: string
{
    case Unknown = 'unknown';
    case None = 'none';
    case Earth = 'earth';
    case Murram = 'murram';
    case Tarmac = 'tarmac';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::None => 'No road recorded',
            self::Earth => 'Earth road',
            self::Murram => 'Murram road',
            self::Tarmac => 'Tarmac road',
        };
    }
}
