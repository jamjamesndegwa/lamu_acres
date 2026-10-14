<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview, self::Accepted, self::Declined, self::Withdrawn],
            self::UnderReview => [self::Accepted, self::Declined, self::Withdrawn],
            self::Accepted, self::Declined, self::Withdrawn => [],
        };
    }
}
