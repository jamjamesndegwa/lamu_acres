<?php

namespace App\Enums;

enum MapLayerKind: string
{
    case CountyBoundaries = 'county_boundaries';
    case LocalityPoints = 'locality_points';
    case LocalityBoundaries = 'locality_boundaries';
    case Infrastructure = 'infrastructure';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::CountyBoundaries => 'County boundaries',
            self::LocalityPoints => 'Named places',
            self::LocalityBoundaries => 'Locality boundaries',
            self::Infrastructure => 'Infrastructure',
            self::Custom => 'Custom overlay',
        };
    }
}
