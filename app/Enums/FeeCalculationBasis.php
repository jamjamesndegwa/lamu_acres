<?php

namespace App\Enums;

enum FeeCalculationBasis: string
{
    case AgreedSalePrice = 'agreed_sale_price';

    public function label(): string
    {
        return match ($this) {
            self::AgreedSalePrice => 'Agreed sale price',
        };
    }
}
