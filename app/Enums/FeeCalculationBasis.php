<?php

namespace App\Enums;

enum FeeCalculationBasis: string
{
    case AgreedSalePrice = 'agreed_sale_price';
    case Fixed = 'fixed';
    case FirstYearRentPercent = 'first_year_rent_percent';
    case MonthlyRentMultiple = 'monthly_rent_multiple';
    case LeasePremiumPercent = 'lease_premium_percent';

    public function label(): string
    {
        return match ($this) {
            self::AgreedSalePrice => 'Agreed sale price',
            self::Fixed => 'Fixed amount',
            self::FirstYearRentPercent => 'Percentage of first-year contracted rent',
            self::MonthlyRentMultiple => 'Multiple of monthly rent',
            self::LeasePremiumPercent => 'Percentage of lease premium',
        };
    }
}
