<?php

namespace App\Enums;

enum LandUse: string
{
    case Agricultural = 'agricultural';
    case Residential = 'residential';
    case Commercial = 'commercial';
    case Conservation = 'conservation';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Agricultural => 'Agricultural',
            self::Residential => 'Residential',
            self::Commercial => 'Commercial',
            self::Conservation => 'Conservation',
            self::Mixed => 'Mixed',
        };
    }
}
