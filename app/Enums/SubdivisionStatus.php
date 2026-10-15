<?php

namespace App\Enums;

enum SubdivisionStatus: string
{
    case NotRequired = 'not_required';
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Registered = 'registered';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required (whole parcel)',
            self::NotStarted => 'Not started',
            self::InProgress => 'Subdivision in progress',
            self::Registered => 'Subdivision registered',
        };
    }
}
