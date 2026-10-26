<?php

namespace App\Enums;

enum AgencyFeePayer: string
{
    case Seller = 'seller';
    case Buyer = 'buyer';

    public function label(): string
    {
        return match ($this) {
            self::Seller => 'Seller',
            self::Buyer => 'Buyer',
        };
    }
}
