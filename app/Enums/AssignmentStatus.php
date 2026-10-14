<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Scheduled = 'scheduled';
    case Due = 'due';
    case Overdue = 'overdue';
    case Published = 'published';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Due => 'Due',
            self::Overdue => 'Overdue',
            self::Published => 'Published',
            self::Unavailable => 'No usable source',
        };
    }
}
