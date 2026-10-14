<?php

namespace App\Enums;

enum SaleInstructionKind: string
{
    case Listing = 'listing';
    case Availability = 'availability';
    case OfferResponse = 'offer_response';
    case Viewing = 'viewing';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Listing => 'Listing',
            self::Availability => 'Availability',
            self::OfferResponse => 'Offer response',
            self::Viewing => 'Viewing',
            self::General => 'General',
        };
    }
}
