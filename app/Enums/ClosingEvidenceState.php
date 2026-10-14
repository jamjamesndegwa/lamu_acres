<?php

namespace App\Enums;

enum ClosingEvidenceState: string
{
    case Missing = 'missing';
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Missing',
            self::Received => 'Received',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }
}
