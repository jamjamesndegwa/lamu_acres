<?php

namespace App\Enums;

enum SaleMode: string
{
    case Whole = 'whole';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'Entire parcel only',
            self::Partial => 'Partial purchases allowed',
        };
    }
}
