<?php

namespace App\Enums;

enum Amenity: string
{
    case RoadAccess = 'road_access';
    case Water = 'water';
    case Electricity = 'electricity';
    case Borehole = 'borehole';
    case Fencing = 'fencing';
    case OceanFront = 'ocean_front';

    public function label(): string
    {
        return match ($this) {
            self::RoadAccess => 'Road access',
            self::Water => 'Water',
            self::Electricity => 'Electricity',
            self::Borehole => 'Borehole',
            self::Fencing => 'Fencing',
            self::OceanFront => 'Ocean front',
        };
    }
}
