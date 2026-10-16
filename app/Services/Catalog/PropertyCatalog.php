<?php

namespace App\Services\Catalog;

use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PropertyCatalog
{
    /**
     * @return array{q: ?string, purpose: ?ListingPurpose, type: ?PropertyType, min_price: ?float, max_price: ?float, min_price_per_acre: ?float, max_price_per_acre: ?float, min_acres: ?float, max_acres: ?float, sort: string, view: string}
     */
    public function filters(Request $request): array
    {
        return [
            'q' => $this->text($request, 'q'),
            'purpose' => ListingPurpose::tryFrom((string) $request->query('purpose', '')),
            'type' => PropertyType::tryFrom((string) $request->query('type', '')),
            'min_price' => $this->number($request, 'min_price'),
            'max_price' => $this->number($request, 'max_price'),
            'min_price_per_acre' => $this->number($request, 'min_price_per_acre'),
            'max_price_per_acre' => $this->number($request, 'max_price_per_acre'),
            'min_acres' => $this->number($request, 'min_acres'),
            'max_acres' => $this->number($request, 'max_acres'),
            'sort' => $this->sort($request),
            'view' => $request->query('view') === 'list' ? 'list' : 'cards',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listings(array $filters, ?County $county = null, ?Locality $locality = null): LengthAwarePaginator
    {
        return $this->query($filters, $county, $locality)
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Property>
     */
    public function featured(int $limit = 6): Collection
    {
        return Property::query()
            ->publiclyListed()
            ->where('featured', true)
            ->with(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id'])
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, County>
     */
    public function counties(): Collection
    {
        return County::query()
            ->active()
            ->with(['localities' => function ($query): void {
                $query->active()
                    ->withCount(['properties as available_listings_count' => fn (Builder $listings) => $listings->publiclyListed()])
                    ->orderBy('name');
            }])
            ->withCount(['properties as available_listings_count' => fn (Builder $query) => $query->publiclyListed()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Locality>
     */
    public function localities(?County $county = null): Collection
    {
        return Locality::query()
            ->active()
            ->with('county:id,name,slug')
            ->when($county, fn (Builder $query) => $query->where('county_id', $county->id))
            ->withCount(['properties as available_listings_count' => fn (Builder $query) => $query->publiclyListed()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters, ?County $county = null, ?Locality $locality = null): Builder
    {
        $query = Property::query()
            ->publiclyListed()
            ->with(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        if ($locality) {
            $query->where('locality_id', $locality->id);
        } elseif ($county) {
            $query->where('county_id', $county->id);
        }

        if (is_string($filters['q']) && $filters['q'] !== '') {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('county', 'like', $term)
                    ->orWhere('locality', 'like', $term);
            });
        }

        if ($filters['purpose'] instanceof ListingPurpose) {
            $query->where('purpose', $filters['purpose']);
        }

        if ($filters['type'] instanceof PropertyType) {
            $query->where('property_type', $filters['type']);
        }

        $this->between($query, 'price_amount', $filters['min_price'], $filters['max_price']);
        $this->between($query, 'acreage', $filters['min_acres'], $filters['max_acres']);
        $this->pricePerAcre($query, $filters['min_price_per_acre'], $filters['max_price_per_acre']);

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price_amount'),
            'price_desc' => $query->orderByDesc('price_amount'),
            'acres_asc' => $query->orderBy('acreage'),
            'acres_desc' => $query->orderByDesc('acreage'),
            default => $query->latest('published_at'),
        };

        return $query->orderBy('id');
    }

    private function between(Builder $query, string $column, mixed $min, mixed $max): void
    {
        if (is_numeric($min)) {
            $query->where($column, '>=', $min);
        }

        if (is_numeric($max)) {
            $query->where($column, '<=', $max);
        }
    }

    private function pricePerAcre(Builder $query, mixed $min, mixed $max): void
    {
        if (! is_numeric($min) && ! is_numeric($max)) {
            return;
        }

        $query->where('acreage', '>', 0);

        if (is_numeric($min)) {
            $query->whereRaw('CAST(price_amount AS REAL) / CAST(acreage AS REAL) >= CAST(? AS REAL)', [$min]);
        }

        if (is_numeric($max)) {
            $query->whereRaw('CAST(price_amount AS REAL) / CAST(acreage AS REAL) <= CAST(? AS REAL)', [$max]);
        }
    }

    private function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 100);
    }

    private function number(Request $request, string $key): ?float
    {
        $value = $request->query($key);

        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (float) $value;
    }

    private function sort(Request $request): string
    {
        $sort = (string) $request->query('sort', 'newest');

        return in_array($sort, ['newest', 'price_asc', 'price_desc', 'acres_asc', 'acres_desc'], true)
            ? $sort
            : 'newest';
    }
}
