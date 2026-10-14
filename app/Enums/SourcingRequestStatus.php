<?php

namespace App\Enums;

enum SourcingRequestStatus: string
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Matched = 'matched';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InProgress => 'In progress',
            self::Matched => 'Matched',
            self::Closed => 'Closed',
        };
    }
}
