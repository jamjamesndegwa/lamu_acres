<?php

namespace App\Services\Catalog;

use App\Models\County;
use App\Models\Locality;
use App\Services\Geography\LocationDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LandFinder
{
    public function __construct(private PropertyCatalog $catalog, private LocationDirectory $locations) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $input
     * @return array{query: Builder, centre: ?array{latitude: float, longitude: float, label: string}, radius: float}
     */
    public function search(array $filters, array $input, ?County $county, ?Locality $locality): array
    {
        $centre = $this->centre($input, $county, $locality);
        $base = array_replace($filters, ['min_price' => null, 'max_price' => null, 'min_acres' => null, 'max_acres' => null, 'min_price_per_acre' => null, 'max_price_per_acre' => null]);
        $query = $this->catalog->query($base, $centre ? null : $county, $centre ? null : $locality)->reorder();
        $acres = "COALESCE(properties.offered_acreage, CASE WHEN properties.parent_id IS NOT NULL THEN properties.acreage ELSE properties.acreage - COALESCE((SELECT SUM(portion.acreage) FROM properties AS portion WHERE portion.parent_id = properties.id AND portion.status = 'sold'), 0) END)";
        $query->whereRaw($acres.' > 0')->withSum(['portions as sold_portion_acreage' => fn (Builder $portions) => $portions->where('status', 'sold')], 'acreage');
        $budget = "CASE WHEN properties.purpose = 'sale' THEN properties.price_amount WHEN properties.price_basis IN ('parcel_month', 'acre_month') THEN properties.price_amount WHEN properties.price_basis IN ('parcel_year', 'acre_year') THEN properties.price_amount / 12.0 ELSE NULL END";
        if (($input['budget_basis'] ?? 'total') === 'per_acre') {
            $budget = '('.$budget.') / NULLIF(('.$acres.'), 0)';
        }
        foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
            if ($filters[$bound.'_acres'] !== null) {
                $query->whereRaw($acres.' '.$operator.' CAST(? AS DECIMAL(24, 8))', [$filters[$bound.'_acres']]);
            }
            if ($filters[$bound.'_price'] !== null) {
                $query->where('price_currency', 'KES')->whereRaw('('.$budget.') '.$operator.' CAST(? AS DECIMAL(24, 8))', [$filters[$bound.'_price']]);
            }
        }
        $radius = (float) ($input['radius'] ?? 25);
        if ($centre !== null) {
            $this->nearby($query, $centre['latitude'], $centre['longitude'], $radius);
        }
        $sort = $input['sort'] ?? ($centre ? 'nearest' : 'newest');
        if (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $query->where('price_currency', 'KES');
        }
        if ($sort === 'nearest' && $centre === null) {
            throw ValidationException::withMessages(['sort' => 'Choose a nearby search point before sorting by distance.']);
        }
        match ($sort) {
            'nearest' => $query->orderBy('distance_km'),
            'price_asc' => $query->orderByRaw('('.$budget.') IS NULL')->orderByRaw('('.$budget.') ASC'),
            'price_desc' => $query->orderByRaw('('.$budget.') IS NULL')->orderByRaw('('.$budget.') DESC'),
            'acres_asc' => $query->orderByRaw($acres.' ASC'),
            'acres_desc' => $query->orderByRaw($acres.' DESC'),
            default => $query->latest('published_at'),
        };

        return ['query' => $query->orderBy('properties.id'), 'centre' => $centre, 'radius' => $radius];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{latitude: float, longitude: float, label: string}|null
     */
    private function centre(array $input, ?County $county, ?Locality $locality): ?array
    {
        if (($input['mode'] ?? (isset($input['latitude']) ? 'nearby' : 'area')) !== 'nearby') {
            return null;
        }
        if (isset($input['latitude'], $input['longitude'])) {
            return ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude'], 'label' => 'Your selected search point'];
        }
        $place = $locality ?? $county;
        if ($place === null || ! $place->hasPoint() || abs((float) $place->latitude) > 90 || abs((float) $place->longitude) > 180) {
            throw ValidationException::withMessages(['mode' => 'Choose a registered location with a map centre, enter coordinates, or use your location. You can also search by county/locality without a radius.']);
        }

        return ['latitude' => (float) $place->latitude, 'longitude' => (float) $place->longitude, 'label' => $place->name.' registered centre'];
    }

    private function nearby(Builder $query, float $latitude, float $longitude, float $radius): void
    {
        $latSpan = rad2deg($radius / 6371);
        $query->whereBetween('public_latitude', [max(-90, $latitude - $latSpan - 0.0051), min(90, $latitude + $latSpan + 0.0051)])
            ->whereBetween('public_longitude', [-180, 180]);
        if (abs($latitude) + $latSpan < 90) {
            $lngSpan = rad2deg(asin(min(1, sin($radius / 6371) / cos(deg2rad($latitude))))) + 0.0051;
            $west = $longitude - $lngSpan;
            $east = $longitude + $lngSpan;
            $query->where(function (Builder $bounds) use ($west, $east): void {
                if ($west < -180) {
                    $bounds->where('public_longitude', '>=', $west + 360)->orWhere('public_longitude', '<=', $east);
                } elseif ($east > 180) {
                    $bounds->where('public_longitude', '>=', $west)->orWhere('public_longitude', '<=', $east - 360);
                } else {
                    $bounds->whereBetween('public_longitude', [$west, $east]);
                }
            });
        }
        $query->whereBetween('public_latitude', [-90, 90]);
        $connection = $query->getConnection();
        if ($connection->getDriverName() === 'sqlite') {
            $connection->getPdo()->sqliteCreateFunction('lamu_distance_km', fn (float $lat, float $lng, float $fromLat, float $fromLng): float => $this->locations->haversineMeters($fromLat, $fromLng, $lat, $lng) / 1000, 4);
            $distance = 'lamu_distance_km(ROUND(public_latitude, 2), ROUND(public_longitude, 2), ?, ?)';
            $bindings = [$latitude, $longitude];
        } else {
            $distance = '6371 * 2 * ASIN(SQRT(LEAST(1, GREATEST(0, POWER(SIN(RADIANS(ROUND(public_latitude, 2) - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS(ROUND(public_latitude, 2))) * POWER(SIN(RADIANS(ROUND(public_longitude, 2) - ?) / 2), 2)))))';
            $bindings = [$latitude, $latitude, $longitude];
        }
        $query->addSelect('properties.*')->selectRaw($distance.' AS distance_km', $bindings)
            ->whereRaw('('.$distance.') <= CAST(? AS DECIMAL(24, 8))', [...$bindings, $radius]);
    }
}
