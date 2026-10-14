<?php

namespace App\Enums;

enum CapabilityApplicationStatus: string
{
    case Pending = 'pending';
    case InformationRequired = 'information_required';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InformationRequired => 'Information required',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
        };
    }

    public function isOpenForApplicant(): bool
    {
        return in_array($this, [self::Pending, self::InformationRequired, self::Rejected], true);
    }
}
