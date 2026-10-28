<?php

namespace App\Enums;

enum MessageDeliveryStatus: string
{
    case NotSent = 'not_sent';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Uncertain = 'uncertain';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::NotSent => 'Not sent',
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Uncertain => 'Uncertain',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * @return list<string>
     */
    public static function summaryKeys(): array
    {
        return [
            self::NotSent->value,
            self::Queued->value,
            self::Sent->value,
            self::Failed->value,
            self::Uncertain->value,
            self::Skipped->value,
        ];
    }
}
