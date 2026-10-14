<?php

namespace App\Enums;

enum PartnerCapability: string
{
    case Broker = 'broker';
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Broker => 'Broker',
            self::Seller => 'Seller',
        };
    }
}
