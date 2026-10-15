<?php

namespace App\Enums;

enum OccupationStatus: string
{
    case Vacant = 'vacant';
    case OwnerOccupied = 'owner_occupied';
    case TenantOccupied = 'tenant_occupied';
    case InformalOccupation = 'informal_occupation';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Vacant => 'Vacant',
            self::OwnerOccupied => 'Occupied by the owner',
            self::TenantOccupied => 'Occupied by a tenant',
            self::InformalOccupation => 'Informal occupation',
            self::Unknown => 'Not known',
        };
    }
}
