<?php

namespace App\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'In review',
            self::Published => 'Published',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
