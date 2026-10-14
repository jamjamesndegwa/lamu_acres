<?php

namespace App\Enums;

enum VerificationLevel: string
{
    case Unreviewed = 'unreviewed';
    case Advertised = 'advertised';
    case DocumentsReceived = 'documents_received';
    case OwnershipChecked = 'ownership_checked';
    case Enhanced = 'enhanced';

    public function label(): string
    {
        return match ($this) {
            self::Unreviewed => 'Not reviewed',
            self::Advertised => 'Level 0 — Advertised',
            self::DocumentsReceived => 'Level 1 — Documents received',
            self::OwnershipChecked => 'Level 2 — Ownership and authority checked',
            self::Enhanced => 'Level 3 — Enhanced due diligence',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Unreviewed, self::Advertised => 0,
            self::DocumentsReceived => 1,
            self::OwnershipChecked => 2,
            self::Enhanced => 3,
        };
    }
}
