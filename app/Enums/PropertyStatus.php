<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Withdrawn = 'withdrawn';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'Review',
            self::Published => 'Published',
            self::Reserved => 'Reserved',
            self::Sold => 'Sold',
            self::Withdrawn => 'Withdrawn',
            self::Suspended => 'Suspended',
            self::Archived => 'Archived',
        };
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review],
            self::Review => [self::Draft, self::Published],
            self::Published => [self::Reserved, self::Sold, self::Withdrawn, self::Suspended],
            self::Reserved => [self::Published, self::Sold, self::Withdrawn],
            self::Withdrawn => [self::Draft],
            self::Suspended => [self::Review, self::Withdrawn],
            self::Sold, self::Archived => [],
        };
    }
}
