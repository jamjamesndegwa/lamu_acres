<?php

namespace App\Enums;

enum DocumentCheckState: string
{
    case Unknown = 'unknown';
    case Listed = 'listed';
    case Withheld = 'withheld';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not stated',
            self::Listed => 'Recorded on file',
            self::Withheld => 'Not published',
        };
    }
}
