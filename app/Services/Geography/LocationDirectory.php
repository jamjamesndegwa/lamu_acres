<?php

namespace App\Services\Geography;

use App\Models\Locality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationDirectory
{
    /**
     * @return Collection<int, Locality>
     */
    public function search(string $term, ?int $countyId = null): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Locality::query()
            ->active()
            ->with(['county:id,name,slug', 'parent:id,name,slug'])
            ->when($countyId, fn (Builder $query) => $query->where('county_id', $countyId))
            ->where(function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('name', 'like', $like));
            })
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, Locality>
     */
    public function nearby(float $latitude, float $longitude, float $kilometers = 25): Collection
    {
        $query = Locality::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('county:id,name,slug');

        if (DB::getDriverName() === 'mysql') {
            return $query
                ->select('localities.*')
                ->selectRaw($this->distanceExpression().' as distance_m', [$longitude, $latitude])
                ->whereRaw($this->distanceExpression().' <= ?', [$longitude, $latitude, $kilometers * 1000])
                ->orderBy('distance_m')
                ->limit(20)
                ->get();
        }

        return $query->get()
            ->map(function (Locality $locality) use ($latitude, $longitude): Locality {
                $locality->setAttribute('distance_m', $this->haversineMeters(
                    $latitude,
                    $longitude,
                    (float) $locality->latitude,
                    (float) $locality->longitude,
                ));

                return $locality;
            })
            ->filter(fn (Locality $locality): bool => ((float) $locality->distance_m) <= ($kilometers * 1000))
            ->sortBy('distance_m')
            ->take(20)
            ->values();
    }

    public function distanceExpression(): string
    {
        return 'ST_Distance_Sphere(point(longitude, latitude), point(?, ?))';
    }

    public function haversineMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earth = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;
        $a = max(0, min(1, $a));

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
