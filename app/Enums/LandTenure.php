<?php

namespace App\Enums;

enum LandTenure: string
{
    case Freehold = 'freehold';
    case Leasehold = 'leasehold';
    case Community = 'community';
    case Adjudication = 'adjudication';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Freehold => 'Freehold',
            self::Leasehold => 'Leasehold',
            self::Community => 'Community land',
            self::Adjudication => 'Adjudication-stage interest',
            self::Other => 'Other classification',
        };
    }

    public function needsSpecialistVerification(): bool
    {
        return in_array($this, [self::Community, self::Adjudication, self::Other], true);
    }
}
