<?php

namespace App\Enums;

enum PriceBasis: string
{
    case Total = 'total';
    case PerAcre = 'per_acre';
    case ParcelMonth = 'parcel_month';
    case ParcelYear = 'parcel_year';
    case AcreMonth = 'acre_month';
    case AcreYear = 'acre_year';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Total parcel price',
            self::PerAcre => 'Price per acre',
            self::ParcelMonth => 'Whole parcel per month',
            self::ParcelYear => 'Whole parcel per year',
            self::AcreMonth => 'Per acre per month',
            self::AcreYear => 'Per acre per year',
        };
    }

    public function purpose(): ListingPurpose
    {
        return match ($this) {
            self::Total, self::PerAcre => ListingPurpose::Sale,
            default => ListingPurpose::Lease,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function forPurpose(ListingPurpose $purpose): array
    {
        return array_values(array_filter(self::cases(), fn (self $basis) => $basis->purpose() === $purpose));
    }

    public function isPerAcre(): bool
    {
        return in_array($this, [self::PerAcre, self::AcreMonth, self::AcreYear], true);
    }

    /**
     * Wording shown after the seller-entered rate, e.g. "KSh 50,000 per acre".
     */
    public function suffix(): string
    {
        return match ($this) {
            self::Total => '',
            self::PerAcre => 'per acre',
            self::ParcelMonth => 'per month',
            self::ParcelYear => 'per year',
            self::AcreMonth => 'per acre per month',
            self::AcreYear => 'per acre per year',
        };
    }

    /**
     * Wording shown after the computed amount for the whole offered area.
     */
    public function periodSuffix(): string
    {
        return match ($this) {
            self::ParcelMonth, self::AcreMonth => 'per month',
            self::ParcelYear, self::AcreYear => 'per year',
            default => '',
        };
    }
}
