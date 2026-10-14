<?php

namespace App\Services\Buyer;

use App\Enums\LandUse;
use App\Enums\MatchBand;
use App\Models\Property;
use App\Models\SourcingRequest;
use App\Support\Geo\GeoMath;

class SourcingMatcher
{
    public function refresh(SourcingRequest $request): void
    {
        $request->matches()->delete();
        $request->load('amenities');

        $properties = Property::query()
            ->publiclyListed()
            ->whereNotNull('public_latitude')
            ->whereNotNull('public_longitude')
            ->get();

        foreach ($properties as $property) {
            $assessment = $this->assess($request, $property);

            if ($assessment === null) {
                continue;
            }

            $request->matches()->create([
                'property_id' => $property->id,
                'band' => $assessment['band'],
                'distance_km' => $assessment['distance_km'],
                'reasons' => $assessment['reasons'],
            ]);
        }
    }

    /**
     * @return array{band: MatchBand, distance_km: ?float, reasons: array<int, string>}|null
     */
    public function assess(SourcingRequest $request, Property $property): ?array
    {
        if ($property->public_latitude === null || $property->public_longitude === null) {
            return null;
        }

        $latitude = (float) $property->public_latitude;
        $longitude = (float) $property->public_longitude;
        $distance = $this->distance($request, $latitude, $longitude);
        $geography = $this->geography($request, $latitude, $longitude, $distance);

        if ($geography === null) {
            return null;
        }

        $soft = $this->softGaps($request, $property);

        if ($soft === null) {
            return null;
        }

        $reasons = array_values(array_filter([
            ...$geography['reasons'],
            ...$soft,
            ...$this->preferredNotes($request, $property),
        ]));

        $band = $geography['exact'] && $soft === []
            ? MatchBand::Exact
            : MatchBand::Near;

        if ($band === MatchBand::Exact) {
            $reasons = $this->preferredNotes($request, $property);
        }

        return [
            'band' => $band,
            'distance_km' => $distance,
            'reasons' => array_values($reasons),
        ];
    }

    private function distance(SourcingRequest $request, float $latitude, float $longitude): ?float
    {
        if ($request->centre_latitude === null || $request->centre_longitude === null) {
            return null;
        }

        return round(GeoMath::distanceKm(
            (float) $request->centre_latitude,
            (float) $request->centre_longitude,
            $latitude,
            $longitude,
        ), 3);
    }

    /**
     * @return array{exact: bool, reasons: array<int, string>}|null
     */
    private function geography(SourcingRequest $request, float $latitude, float $longitude, ?float $distance): ?array
    {
        $radius = $request->radius_km !== null ? (float) $request->radius_km : null;
        $ring = $request->ring();
        $inside = $ring !== [] ? GeoMath::contains($latitude, $longitude, $ring) : null;
        $withinRadius = $radius !== null && $distance !== null && $distance <= $radius;
        $withinNearRadius = $radius !== null && $distance !== null && $distance <= ($radius * 1.5);

        if ($ring !== []) {
            if ($inside && ($radius === null || $withinRadius)) {
                return ['exact' => true, 'reasons' => []];
            }

            if ($inside && $withinNearRadius) {
                return ['exact' => false, 'reasons' => ['Outside the requested radius.']];
            }

            if (! $inside && (GeoMath::withinKilometresOfRing($latitude, $longitude, $ring, 3) || $withinNearRadius)) {
                return ['exact' => false, 'reasons' => ['Outside the drawn area.']];
            }

            return null;
        }

        if ($distance === null || $radius === null) {
            return null;
        }

        if ($withinRadius) {
            return ['exact' => true, 'reasons' => []];
        }

        if ($withinNearRadius) {
            return ['exact' => false, 'reasons' => ['Outside the requested radius.']];
        }

        return null;
    }

    /**
     * @return array<int, string>|null
     */
    private function softGaps(SourcingRequest $request, Property $property): ?array
    {
        $gaps = [];
        $acreage = $property->acreage !== null ? (float) $property->acreage : null;
        $price = $property->price_amount !== null ? (int) $property->price_amount : null;

        if ($request->min_acreage !== null) {
            $minimum = (float) $request->min_acreage;

            if ($acreage === null || $acreage < $minimum) {
                if ($acreage !== null && $acreage >= $minimum * 0.75) {
                    $gaps[] = 'Below the minimum acreage.';
                } else {
                    return null;
                }
            }
        }

        if ($request->max_acreage !== null) {
            $maximum = (float) $request->max_acreage;

            if ($acreage === null || $acreage > $maximum) {
                if ($acreage !== null && $acreage <= $maximum * 1.25) {
                    $gaps[] = 'Above the maximum acreage.';
                } else {
                    return null;
                }
            }
        }

        if ($request->maximum_budget !== null) {
            $budget = (int) $request->maximum_budget;

            if ($price === null || $price > $budget) {
                if ($price !== null && $price <= (int) floor($budget * 1.15)) {
                    $gaps[] = 'Above the maximum budget.';
                } else {
                    return null;
                }
            }
        }

        if ($request->intended_use instanceof LandUse) {
            $use = $property->land_use;
            $compatible = $use === $request->intended_use
                || $use === LandUse::Mixed
                || $request->intended_use === LandUse::Mixed;

            if (! $compatible) {
                $gaps[] = 'Different intended land use.';
            }
        }

        $mandatory = $request->amenities
            ->where('required', true)
            ->map(fn ($amenity) => $amenity->amenity->value)
            ->all();
        $missing = array_values(array_diff($mandatory, $this->amenityValues($property)));

        if ($missing !== []) {
            $gaps[] = 'Missing mandatory amenities.';
        }

        return $gaps;
    }

    /**
     * @return array<int, string>
     */
    private function preferredNotes(SourcingRequest $request, Property $property): array
    {
        $preferred = $request->amenities
            ->where('required', false)
            ->map(fn ($amenity) => $amenity->amenity->value)
            ->all();
        $missing = array_values(array_diff($preferred, $this->amenityValues($property)));

        return $missing === [] ? [] : ['Missing preferred amenities.'];
    }

    /**
     * @return array<int, string>
     */
    private function amenityValues(Property $property): array
    {
        return array_values(array_map(
            fn (mixed $amenity): string => $amenity instanceof \BackedEnum ? (string) $amenity->value : (string) $amenity,
            $property->amenities ?? [],
        ));
    }
}
