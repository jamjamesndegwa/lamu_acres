<?php

namespace App\Services\Catalog;

use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Temporary design preview cards for the public land directory.
 *
 * Replace with PropertyCatalog::listings() once published listings are wired into the redesigned directory.
 */
class PreviewLandListings
{
    /**
     * @return Collection<int, array{title: string, locality: string, county: string, location: string, purpose: ListingPurpose, type: ?PropertyType, price: int, acres: float, image: string}>
     */
    public function all(): Collection
    {
        $listing = [
            'title' => 'Mpeketoni Section b',
            'locality' => 'Mpeketoni',
            'county' => 'Lamu',
            'location' => 'Mpeketoni Lamu',
            'purpose' => ListingPurpose::Sale,
            'type' => null,
            'price' => 20_000_000,
            'acres' => 20.0,
            'image' => 'images/default_land_image.jpg',
        ];

        return collect(array_fill(0, 20, $listing));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{title: string, locality: string, county: string, location: string, purpose: ListingPurpose, type: ?PropertyType, price: int, acres: float, image: string}>
     */
    public function paginate(array $filters, ?County $county = null, ?Locality $locality = null, int $perPage = 15): LengthAwarePaginator
    {
        $listings = $this->matching($filters, $county, $locality);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator(
            $listings->forPage($page, $perPage)->values(),
            $listings->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{title: string, locality: string, county: string, location: string, purpose: ListingPurpose, type: ?PropertyType, price: int, acres: float, image: string}>
     */
    public function matching(array $filters, ?County $county = null, ?Locality $locality = null): Collection
    {
        return $this->all()
            ->filter(fn (array $listing): bool => $this->matches($listing, $filters, $county, $locality))
            ->values();
    }

    /**
     * @param  array{title: string, locality: string, county: string, location: string, purpose: ListingPurpose, type: ?PropertyType, price: int, acres: float, image: string}  $listing
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $listing, array $filters, ?County $county, ?Locality $locality): bool
    {
        if ($county !== null && strcasecmp($county->name, $listing['county']) !== 0) {
            return false;
        }

        if ($locality !== null && strcasecmp($locality->name, $listing['locality']) !== 0) {
            return false;
        }

        if (is_string($filters['q']) && $filters['q'] !== '') {
            $haystack = mb_strtolower($listing['title'].' '.$listing['location']);

            if (! str_contains($haystack, mb_strtolower($filters['q']))) {
                return false;
            }
        }

        if ($filters['purpose'] instanceof ListingPurpose && $filters['purpose'] !== $listing['purpose']) {
            return false;
        }

        if ($filters['type'] instanceof PropertyType && $filters['type'] !== $listing['type']) {
            return false;
        }

        $pricePerAcre = $listing['acres'] > 0 ? $listing['price'] / $listing['acres'] : null;

        return $this->within($listing['price'], $filters['min_price'], $filters['max_price'])
            && $this->within($listing['acres'], $filters['min_acres'], $filters['max_acres'])
            && $this->within($pricePerAcre, $filters['min_price_per_acre'], $filters['max_price_per_acre']);
    }

    private function within(int|float|null $value, mixed $min, mixed $max): bool
    {
        if (! is_numeric($min) && ! is_numeric($max)) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        return (! is_numeric($min) || $value >= $min) && (! is_numeric($max) || $value <= $max);
    }
}
