<?php

namespace App\Services\Intelligence;

use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyPriceChange;
use App\Models\VerifiedSale;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PriceObservatory
{
    /**
     * Asking prices only. Verified sales are returned separately and are not part of the median.
     *
     * @return Collection<int, array{locality: Locality, median: ?int, sample: int, fresh_at: mixed, listings: Collection<int, Property>, history: Collection<int, array{at: mixed, label: string, amount: int, per_acre: ?int}>}>
     */
    public function askingByLocality(): Collection
    {
        $listings = Property::query()
            ->publiclyListed()
            ->where('acreage', '>', 0)
            ->whereNotNull('locality_id')
            ->with('areaLocality')
            ->get();

        return $listings
            ->groupBy('locality_id')
            ->map(fn (Collection $group): array => $this->askingRowFromListings($group))
            ->filter(fn (array $row): bool => $row['locality'] !== null && $row['sample'] > 0)
            ->values();
    }

    /**
     * @return array{locality: ?Locality, median: ?int, sample: int, min_median_sample: int, fresh_at: mixed, listings: Collection<int, Property>, history: Collection<int, array{at: mixed, label: string, amount: int, per_acre: ?int}>}|null
     */
    public function askingForLocality(?Locality $locality): ?array
    {
        if ($locality === null) {
            return null;
        }

        /** @var EloquentCollection<int, Property> $listings */
        $listings = Property::query()
            ->publiclyListed()
            ->where('locality_id', $locality->id)
            ->where('acreage', '>', 0)
            ->with('areaLocality')
            ->get();

        if ($listings->isEmpty()) {
            return null;
        }

        $row = $this->askingRowFromListings($listings);

        return $row['sample'] > 0 ? $row : null;
    }

    /**
     * @param  Collection<int, Property>|EloquentCollection<int, Property>  $group
     * @return array{locality: ?Locality, median: ?int, sample: int, min_median_sample: int, fresh_at: mixed, listings: Collection<int, Property>, history: Collection<int, array{at: mixed, label: string, amount: int, per_acre: ?int}>}
     */
    private function askingRowFromListings(Collection|EloquentCollection $group): array
    {
        $minimumSample = $this->minMedianSample();

        $prices = $group
            ->map(fn (Property $property): ?int => $property->pricePerAcre())
            ->filter(fn (?int $price): bool => $price !== null)
            ->sort()
            ->values();

        $history = PropertyPriceChange::query()
            ->whereIn('property_id', $group->modelKeys())
            ->orderBy('created_at')
            ->get()
            ->map(fn (PropertyPriceChange $change): array => [
                'at' => $change->created_at,
                'label' => 'Asking price change',
                'amount' => (int) $change->price_amount,
                'per_acre' => null,
            ]);

        foreach ($group as $property) {
            $history->push([
                'at' => $property->published_at,
                'label' => 'Current asking price',
                'amount' => (int) $property->price_amount,
                'per_acre' => $property->pricePerAcre(),
            ]);
        }

        $sample = $prices->count();

        return [
            'locality' => $group->first()->areaLocality,
            'median' => $sample >= $minimumSample ? $this->median($prices) : null,
            'sample' => $sample,
            'min_median_sample' => $minimumSample,
            'fresh_at' => $group->max('published_at'),
            'listings' => $group->values(),
            'history' => $history->sortBy('at')->values(),
        ];
    }

    public function minMedianSample(): int
    {
        return max(1, (int) config('intelligence.price_observatory.min_median_sample', 3));
    }

    public function verifiedSales(): Collection
    {
        return VerifiedSale::query()->released()->with('locality')->orderByDesc('sold_on')->get();
    }

    /**
     * @param  Collection<int, int>  $prices
     */
    public function median(Collection $prices): ?int
    {
        $count = $prices->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (int) $prices[$middle];
        }

        return (int) round((((int) $prices[$middle - 1]) + ((int) $prices[$middle])) / 2);
    }
}
