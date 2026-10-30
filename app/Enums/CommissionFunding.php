<?php

namespace App\Enums;

enum CommissionFunding: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Lessor = 'lessor';
    case Brokerage = 'brokerage';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Seller => 'Seller',
            self::Lessor => 'Lessor',
            self::Brokerage => 'Brokerage',
        };
    }
}
