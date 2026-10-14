<?php

namespace App\Enums;

enum VerificationItemState: string
{
    case NotSupplied = 'not_supplied';
    case Received = 'received';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Failed = 'failed';
    case Expired = 'expired';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::NotSupplied => 'Not supplied',
            self::Received => 'Received',
            self::UnderReview => 'Under review',
            self::Verified => 'Verified',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::NotApplicable => 'Not applicable',
        };
    }

    public function countsAsVerified(): bool
    {
        return $this === self::Verified;
    }
}
