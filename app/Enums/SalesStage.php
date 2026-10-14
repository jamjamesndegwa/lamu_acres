<?php

namespace App\Enums;

enum SalesStage: string
{
    case Enquiry = 'enquiry';
    case Viewing = 'viewing';
    case Offer = 'offer';
    case Negotiation = 'negotiation';
    case OfferAccepted = 'offer_accepted';
    case Contracting = 'contracting';
    case CompletionPending = 'completion_pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Enquiry',
            self::Viewing => 'Viewing',
            self::Offer => 'Offer',
            self::Negotiation => 'Negotiation',
            self::OfferAccepted => 'Offer Accepted',
            self::Contracting => 'Contracting',
            self::CompletionPending => 'Completion Pending',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Enquiry => [self::Viewing],
            self::Viewing => [self::Offer],
            self::Offer => [self::Negotiation],
            self::Negotiation => [self::OfferAccepted],
            self::OfferAccepted => [self::Contracting],
            self::Contracting => [self::CompletionPending],
            self::CompletionPending, self::Completed => [],
        };
    }
}
