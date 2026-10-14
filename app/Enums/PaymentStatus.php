<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case TimedOut = 'timed_out';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Paid',
            self::Failed => 'Failed',
            self::TimedOut => 'Timed out',
        };
    }
}
