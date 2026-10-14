<?php

namespace App\Enums;

enum PlanningInstrumentStatus: string
{
    case Proposed = 'proposed';
    case InForce = 'in_force';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::InForce => 'In force',
            self::Superseded => 'Superseded',
        };
    }
}
