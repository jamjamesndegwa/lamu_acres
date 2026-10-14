<?php

namespace App\Enums;

enum EnquiryStatus: string
{
    case New = 'new';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Responded = 'responded';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Acknowledged => 'Acknowledged',
            self::InProgress => 'In progress',
            self::Responded => 'Responded',
            self::Closed => 'Closed',
        };
    }
}
