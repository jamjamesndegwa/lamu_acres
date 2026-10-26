<?php

namespace App\Services\Sales;

use App\Enums\ListingPurpose;
use App\Enums\SaleMode;
use App\Models\Property;
use Illuminate\Validation\ValidationException;

class SaleModeTerms
{
    public function resolve(Property $property): SaleMode
    {
        $stored = $property->listingTerm('sale_mode');

        if ($stored === SaleMode::Partial->value) {
            return SaleMode::Partial;
        }

        if ($stored === SaleMode::Whole->value) {
            return SaleMode::Whole;
        }

        if ($property->minimum_acreage !== null && (float) $property->minimum_acreage > 0) {
            return SaleMode::Partial;
        }

        if ($property->offered_acreage !== null
            && (float) $property->offered_acreage + 0.0001 < (float) $property->acreage) {
            return SaleMode::Partial;
        }

        return SaleMode::Whole;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyListingInput(Property $property, array $data): void
    {
        if (($property->purpose ?? ListingPurpose::tryFrom((string) ($data['purpose'] ?? ''))) === ListingPurpose::Lease) {
            return;
        }

        $mode = SaleMode::tryFrom((string) ($data['sale_mode'] ?? '')) ?? $this->resolve($property);
        $terms = $property->listing_terms ?? [];
        $terms['sale_mode'] = $mode->value;
        $property->listing_terms = $terms;

        if ($mode === SaleMode::Whole) {
            $property->offered_acreage = null;
            $property->minimum_acreage = null;

            return;
        }

        $acreage = filled($data['acreage'] ?? null) ? (float) $data['acreage'] : (float) $property->acreage;
        $offered = filled($data['offered_acreage'] ?? null) ? (float) $data['offered_acreage'] : $acreage;
        $property->offered_acreage = min($offered, $acreage);
        $property->minimum_acreage = filled($data['minimum_acreage'] ?? null) ? (float) $data['minimum_acreage'] : null;
    }

    public function validatePurchaseAcreage(Property $property, ?float $requestedAcreage): void
    {
        if ($property->isLease()) {
            return;
        }

        $available = round($property->offeredAcreage(), 4);

        if ($this->resolve($property) === SaleMode::Whole) {
            if ($requestedAcreage !== null && abs($requestedAcreage - $available) > 0.0001) {
                throw ValidationException::withMessages([
                    'requested_acreage' => 'This listing is sold as one complete parcel ('.rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.').' acres). Send a general enquiry if you need a smaller area.',
                ]);
            }

            return;
        }

        if ($requestedAcreage === null) {
            return;
        }

        $requested = round($requestedAcreage, 4);
        $minimum = round((float) ($property->minimum_acreage ?? 0), 4);

        if ($requested > $available + 0.0001) {
            throw ValidationException::withMessages([
                'requested_acreage' => 'The requested acreage exceeds the area currently available.',
            ]);
        }

        if ($minimum > 0 && $requested + 0.0001 < $minimum) {
            throw ValidationException::withMessages([
                'requested_acreage' => 'The requested acreage is below the seller’s minimum portion.',
            ]);
        }
    }

    public function validateTransactionAcreage(Property $property, float $acreage): void
    {
        if ($property->isLease()) {
            return;
        }

        $requested = round($acreage, 4);
        $remaining = round($property->remainingAcreage(), 4);

        if ($requested > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'acreage' => 'The sale acreage is larger than the land still held.',
            ]);
        }

        if ($this->resolve($property) === SaleMode::Whole && abs($requested - $remaining) > 0.0001) {
            throw ValidationException::withMessages([
                'acreage' => 'This listing authorizes sale of the entire remaining parcel only ('.rtrim(rtrim(number_format($remaining, 4, '.', ''), '0'), '.').' acres).',
            ]);
        }

        $minimum = round((float) ($property->minimum_acreage ?? 0), 4);

        if ($this->resolve($property) === SaleMode::Partial
            && $minimum > 0
            && $requested + 0.0001 < $minimum
            && abs($requested - $remaining) > 0.0001) {
            throw ValidationException::withMessages([
                'acreage' => 'The agreed acreage is below the seller’s minimum portion.',
            ]);
        }
    }

    public function indicativePrice(Property $property, float $acreage): ?int
    {
        if ($property->price_amount === null) {
            return null;
        }

        if ($property->priceBasis()->isPerAcre() && $property->price_rate !== null) {
            return (int) round((int) $property->price_rate * $acreage);
        }

        $offered = $property->offeredAcreage();

        if ($offered <= 0) {
            return null;
        }

        return (int) round(((int) $property->price_amount) * ($acreage / $offered));
    }
}
