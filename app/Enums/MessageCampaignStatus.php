<?php

namespace App\Enums;

enum MessageCampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
