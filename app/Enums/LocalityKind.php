<?php

namespace App\Enums;

enum LocalityKind: string
{
    case SubCounty = 'sub_county';
    case Ward = 'ward';
    case Place = 'place';

    public function label(): string
    {
        return match ($this) {
            self::SubCounty => 'Sub-county',
            self::Ward => 'Ward',
            self::Place => 'Place',
        };
    }
}
