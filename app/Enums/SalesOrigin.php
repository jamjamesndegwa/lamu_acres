<?php

namespace App\Enums;

enum SalesOrigin: string
{
    case Enquiry = 'enquiry';
    case Sourcing = 'sourcing';
    case Introduction = 'introduction';
    case Instruction = 'instruction';
    case Offer = 'offer';

    public function label(): string
    {
        return match ($this) {
            self::Enquiry => 'Buyer enquiry',
            self::Sourcing => 'Buyer sourcing request',
            self::Introduction => 'Broker introduction',
            self::Instruction => 'Seller instruction',
            self::Offer => 'Buyer offer',
        };
    }
}
