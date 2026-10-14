<?php

namespace App\Services\Geography;

use App\Enums\MapLayerKind;
use App\Models\County;
use App\Models\InfrastructureFeature;
use App\Models\Locality;
use App\Models\MapLayer;
use App\Support\Geo\Wkt;
use Illuminate\Database\Eloquent\Builder;

class MapLayerCatalog
{
    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    public function preview(MapLayer $layer): array
    {
        return match ($layer->kind) {
            MapLayerKind::CountyBoundaries => $this->countyBoundaries($layer),
            MapLayerKind::LocalityPoints => $this->localityPoints($layer),
            MapLayerKind::LocalityBoundaries => $this->localityBoundaries($layer),
            MapLayerKind::Infrastructure => $this->infrastructure($layer),
            MapLayerKind::Custom => $this->custom($layer),
        };
    }

    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    private function countyBoundaries(MapLayer $layer): array
    {
        $features = [];
        $missing = [];

        foreach ($this->counties($layer)->get() as $county) {
            $ring = Wkt::latLngRing((string) $county->boundary_wkt);

            if ($ring === []) {
                $missing[] = [
                    'name' => $county->name,
                    'detail' => $county->hasBoundary() ? 'Stored boundary cannot be drawn' : 'Boundary not stored',
                ];

                continue;
            }

            $features[] = $this->feature($county->name, 'County boundary', null, null, $ring, true, 'Verified boundary');
        }

        return compact('features', 'missing');
    }

    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    private function localityPoints(MapLayer $layer): array
    {
        $features = [];
        $missing = [];

        foreach ($this->localities($layer)->get() as $place) {
            if (! $place->hasPoint()) {
                $missing[] = ['name' => $place->name, 'detail' => 'Named point not stored'];

                continue;
            }

            $features[] = $this->feature(
                $place->name,
                $place->kind->label(),
                (float) $place->latitude,
                (float) $place->longitude,
                [],
                false,
                'Named point. This marker is not an administrative boundary.',
            );
        }

        return compact('features', 'missing');
    }

    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    private function localityBoundaries(MapLayer $layer): array
    {
        $features = [];
        $missing = [];

        foreach ($this->localities($layer)->get() as $place) {
            $ring = Wkt::latLngRing((string) $place->boundary_wkt);

            if ($ring === []) {
                $missing[] = [
                    'name' => $place->name,
                    'detail' => $place->hasBoundary() ? 'Stored boundary cannot be drawn' : 'Boundary not stored',
                ];

                continue;
            }

            $features[] = $this->feature($place->name, $place->kind->label(), null, null, $ring, true, 'Verified boundary');
        }

        return compact('features', 'missing');
    }

    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    private function infrastructure(MapLayer $layer): array
    {
        $features = [];
        $missing = [];
        $records = InfrastructureFeature::query()
            ->active()
            ->when($layer->county_id, fn (Builder $query) => $query->where('county_id', $layer->county_id))
            ->orderBy('name')
            ->get();

        foreach ($records as $record) {
            $ring = Wkt::latLngRing((string) $record->boundary_wkt);

            if (! $record->hasPoint() && $ring === []) {
                $missing[] = [
                    'name' => $record->name,
                    'detail' => $record->hasBoundary() ? 'Stored boundary cannot be drawn' : 'Point and boundary not stored',
                ];

                continue;
            }

            $features[] = $this->feature(
                $record->name,
                $record->kind->label(),
                $record->hasPoint() ? (float) $record->latitude : null,
                $record->hasPoint() ? (float) $record->longitude : null,
                $ring,
                $ring !== [],
                $ring !== [] ? 'Verified boundary' : 'Named point. Boundary not stored.',
            );
        }

        return compact('features', 'missing');
    }

    /**
     * @return array{features: array<int, array<string, mixed>>, missing: array<int, array{name: string, detail: string}>}
     */
    private function custom(MapLayer $layer): array
    {
        $ring = Wkt::latLngRing((string) $layer->boundary_wkt);

        if (! $layer->hasPoint() && $ring === []) {
            return [
                'features' => [],
                'missing' => [[
                    'name' => $layer->name,
                    'detail' => $layer->hasBoundary() ? 'Stored boundary cannot be drawn' : 'Point and boundary not stored',
                ]],
            ];
        }

        return [
            'features' => [
                $this->feature(
                    $layer->name,
                    'Custom overlay',
                    $layer->hasPoint() ? (float) $layer->latitude : null,
                    $layer->hasPoint() ? (float) $layer->longitude : null,
                    $ring,
                    $ring !== [],
                    $ring !== [] ? 'Verified boundary' : 'Named point. Boundary not stored.',
                ),
            ],
            'missing' => [],
        ];
    }

    private function counties(MapLayer $layer): Builder
    {
        return County::query()
            ->when($layer->county_id, fn (Builder $query) => $query->whereKey($layer->county_id))
            ->orderBy('name');
    }

    private function localities(MapLayer $layer): Builder
    {
        return Locality::query()
            ->active()
            ->when($layer->county_id, fn (Builder $query) => $query->where('county_id', $layer->county_id))
            ->orderBy('name');
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $ring
     * @return array<string, mixed>
     */
    private function feature(string $name, string $kind, ?float $latitude, ?float $longitude, array $ring, bool $boundaryStored, string $note): array
    {
        return [
            'name' => $name,
            'kind' => $kind,
            'lat' => $latitude,
            'lng' => $longitude,
            'ring' => $ring,
            'boundaryStored' => $boundaryStored,
            'note' => $note,
        ];
    }
}
