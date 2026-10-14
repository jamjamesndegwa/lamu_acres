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

    public function statement(): string
    {
        return match ($this) {
            self::Proposed => 'This project is recorded as proposed. It is not described as operating.',
            self::Approved => 'This project is recorded as approved. Approval is not the same as operation.',
            self::GroundbreakingReported => 'A groundbreaking has been reported. The project is not recorded as operating.',
            self::UnderConstruction => 'This project is recorded as under construction. It is not recorded as operating.',
            self::Operational => 'The published research record marks this project operational.',
            self::Suspended => 'This project is recorded as suspended. It is not described as operating.',
            self::Cancelled => 'This project is recorded as cancelled. It is not described as operating.',
        };
    }
}
