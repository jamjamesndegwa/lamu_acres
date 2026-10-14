<?php

namespace App\Enums;

enum LandUse: string
{
    case Agricultural = 'agricultural';
    case Residential = 'residential';
    case Tourism = 'tourism';
    case Industrial = 'industrial';
    case Commercial = 'commercial';
    case Investment = 'investment';
    case Conservation = 'conservation';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Agricultural => 'Agricultural',
            self::Residential => 'Residential',
            self::Tourism => 'Tourism',
            self::Industrial => 'Industrial',
            self::Commercial => 'Commercial',
            self::Investment => 'Investment',
            self::Conservation => 'Conservation',
            self::Mixed => 'Mixed',
        };
    }
}
