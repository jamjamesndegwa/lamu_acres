<?php

namespace App\Enums;

enum ResearchCommitment: string
{
    case WeeklyInfrastructure = 'weekly_infrastructure';
    case MonthlyBulletin = 'monthly_bulletin';
    case MonthlySatellite = 'monthly_satellite';
    case QuarterlyImagery = 'quarterly_imagery';
    case QuarterlyMarket = 'quarterly_market';

    public function label(): string
    {
        return match ($this) {
            self::WeeklyInfrastructure => 'Weekly infrastructure research review',
            self::MonthlyBulletin => 'Monthly investment bulletin',
            self::MonthlySatellite => 'Monthly satellite review',
            self::QuarterlyImagery => 'Quarterly imagery comparison',
            self::QuarterlyMarket => 'Quarterly land market report',
        };
    }

    public function cadence(): string
    {
        return match ($this) {
            self::WeeklyInfrastructure => 'week',
            self::MonthlyBulletin, self::MonthlySatellite => 'month',
            self::QuarterlyImagery, self::QuarterlyMarket => 'quarter',
        };
    }

    public function expectsImagery(): bool
    {
        return $this === self::QuarterlyImagery;
    }
}
