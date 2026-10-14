<?php

namespace App\Enums;

enum DevelopmentProjectStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case GroundbreakingReported = 'groundbreaking_reported';
    case UnderConstruction = 'under_construction';
    case Operational = 'operational';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Approved => 'Approved',
            self::GroundbreakingReported => 'Groundbreaking Reported',
            self::UnderConstruction => 'Under Construction',
            self::Operational => 'Operational',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }
}
