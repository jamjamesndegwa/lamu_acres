<?php

namespace App\Services\Catalog;

use App\Enums\ListingPurpose;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AppliedLandFilters
{
    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, url: string}>
     */
    public function chips(Request $request, array $filters, ?County $county = null, ?Locality $locality = null, string $indexRoute = 'land.index'): array
    {
        $chips = [];

        if ($county !== null) {
            $chips[] = [
                'label' => 'Area: '.($locality !== null ? $locality->name.', ' : '').$county->name,
                'url' => $this->url($this->indexUrl($indexRoute, $county, $locality), $this->query($request)),
            ];
        }

        if (is_string($filters['q']) && $filters['q'] !== '') {
            $chips[] = $this->chip($request, $county, $locality, 'Location: '.$filters['q'], ['q'], $indexRoute);
        }

        if ($filters['purpose'] instanceof ListingPurpose) {
            $chips[] = $this->chip($request, $county, $locality, $filters['purpose'] === ListingPurpose::Sale ? 'For sale' : 'For lease', ['purpose'], $indexRoute);
        }

        if ($filters['type'] instanceof PropertyType) {
            $chips[] = $this->chip($request, $county, $locality, 'Type: '.$filters['type']->label(), ['type'], $indexRoute);
        }

        foreach ([
            'price' => ['Price', fn (float $value): string => 'KES '.number_format($value)],
            'acres' => ['Acreage', fn (float $value): string => $this->acres($value)],
            'price_per_acre' => ['Price per acre', fn (float $value): string => 'KES '.number_format($value).' /acre'],
        ] as $key => [$name, $format]) {
            $min = $filters['min_'.$key];
            $max = $filters['max_'.$key];

            if ($min === null && $max === null) {
                continue;
            }

            $label = match (true) {
                $min !== null && $max !== null => $name.': '.$format($min).' – '.$format($max),
                $min !== null => 'Min '.mb_strtolower($name).': '.$format($min),
                default => 'Max '.mb_strtolower($name).': '.$format($max),
            };

            $chips[] = $this->chip($request, $county, $locality, $label, ['min_'.$key, 'max_'.$key], $indexRoute);
        }

        return $chips;
    }

    /**
     * Clear every filter while keeping the visitor's sort and view preference.
     */
    public function clearUrl(Request $request, ?County $county = null, ?Locality $locality = null, string $indexRoute = 'land.index'): string
    {
        return $this->url($this->indexUrl($indexRoute, $county, $locality), Arr::only($this->query($request), ['sort', 'view']));
    }

    /**
     * @param  list<string>  $keys
     * @return array{label: string, url: string}
     */
    private function chip(Request $request, ?County $county, ?Locality $locality, string $label, array $keys, string $indexRoute = 'land.index'): array
    {
        return [
            'label' => $label,
            'url' => $this->url($this->indexUrl($indexRoute, $county, $locality), Arr::except($this->query($request), $keys)),
        ];
    }

    private function indexUrl(string $indexRoute, ?County $county, ?Locality $locality): string
    {
        return route($indexRoute, array_filter([
            'county' => $county?->slug,
            'locality' => $locality?->slug,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function query(Request $request): array
    {
        return Arr::except(
            array_filter($request->query(), fn (mixed $value): bool => $value !== null && $value !== ''),
            ['page'],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function url(string $base, array $query): string
    {
        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    private function acres(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2), '0'), '.');

        return $formatted.' '.($value == 1.0 ? 'acre' : 'acres');
    }
}
