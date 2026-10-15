<?php

namespace App\Enums;

enum BoundaryStatus: string
{
    case Identified = 'identified';
    case PartlyIdentified = 'partly_identified';
    case NotIdentified = 'not_identified';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Identified => 'Beacons identified',
            self::PartlyIdentified => 'Some beacons identified',
            self::NotIdentified => 'Beacons not identified',
            self::Unknown => 'Not known',
        };
    }
}
