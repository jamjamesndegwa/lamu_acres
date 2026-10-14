<?php

namespace App\Enums;

enum EvidenceSensitivity: string
{
    case General = 'general';
    case Title = 'title';
    case OwnerId = 'owner_id';
    case Parcel = 'parcel';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General evidence',
            self::Title => 'Title document',
            self::OwnerId => 'Owner identity',
            self::Parcel => 'Parcel record',
        };
    }

    public function isRestricted(): bool
    {
        return $this !== self::General;
    }
}
