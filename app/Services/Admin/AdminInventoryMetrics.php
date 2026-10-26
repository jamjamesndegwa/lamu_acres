<?php

namespace App\Services\Admin;

use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Services\Inventory\PropertyInventory;
use App\Support\Reporting\ReportingFilters;

class AdminInventoryMetrics
{
    public function __construct(private PropertyInventory $inventory) {}

    /**
     * @return array<string, int|float>
     */
    public function summarize(?ReportingFilters $filters = null): array
    {
        $filters ??= ReportingFilters::empty();

        $publishedCount = (int) $filters->applyToProperties(
            Property::query()
                ->whereNull('parent_id')
                ->where('status', PropertyStatus::Published)
                ->where('availability', PropertyAvailability::Available),
        )->count();

        $registered = 0.0;
        $offered = 0.0;
        $sold = 0.0;
        $remaining = 0.0;
        $available = 0.0;
        $fullySold = 0;
        $partiallySold = 0;
        $wholeOnly = 0;
        $partialMode = 0;

        $filters->applyToProperties(Property::query()->whereNull('parent_id'))
            ->select(['id', 'acreage', 'offered_acreage', 'listing_terms', 'minimum_acreage'])
            ->orderBy('id')
            ->chunkById(100, function ($properties) use (
                &$registered,
                &$offered,
                &$sold,
                &$remaining,
                &$available,
                &$fullySold,
                &$partiallySold,
                &$wholeOnly,
                &$partialMode,
            ): void {
                foreach ($properties as $property) {
                    $snapshot = $this->inventory->inventorySnapshot($property);
                    $registered += $snapshot['registered_acreage'];
                    $offered += $snapshot['offered_acreage'];
                    $sold += $snapshot['sold_acreage'];
                    $remaining += $snapshot['remaining_acreage'];
                    $available += $snapshot['available_acreage'];

                    if ($snapshot['remaining_acreage'] <= 0.0001 && $snapshot['registered_acreage'] > 0) {
                        $fullySold++;
                    } elseif ($snapshot['sold_acreage'] > 0.0001) {
                        $partiallySold++;
                    }

                    if ($property->saleMode()->value === 'whole') {
                        $wholeOnly++;
                    } else {
                        $partialMode++;
                    }
                }
            });

        return [
            'published_listings' => $publishedCount,
            'registered_acreage' => round($registered, 4),
            'offered_acreage' => round($offered, 4),
            'sold_acreage' => round($sold, 4),
            'remaining_acreage' => round($remaining, 4),
            'available_acreage' => round($available, 4),
            'fully_sold_parcels' => $fullySold,
            'partially_sold_parcels' => $partiallySold,
            'whole_sale_listings' => $wholeOnly,
            'partial_sale_listings' => $partialMode,
        ];
    }

    /**
     * @return array<int, array{county: string, locality: string, registered: float, sold: float, remaining: float}>
     */
    public function byLocation(?ReportingFilters $filters = null, int $limit = 10): array
    {
        $filters ??= ReportingFilters::empty();

        $pairs = $filters->applyToProperties(
            Property::query()
                ->whereNull('parent_id')
                ->whereNotNull('county_id')
                ->select('county_id', 'locality_id')
                ->distinct()
                ->with(['areaCounty:id,name', 'areaLocality:id,name'])
                ->limit($limit)
        )->get();

        return $pairs->map(function (Property $pair): array {
            $registered = 0.0;
            $sold = 0.0;
            $remaining = 0.0;

            Property::query()
                ->whereNull('parent_id')
                ->where('county_id', $pair->county_id)
                ->when($pair->locality_id, fn ($query) => $query->where('locality_id', $pair->locality_id))
                ->when($pair->locality_id === null, fn ($query) => $query->whereNull('locality_id'))
                ->orderBy('id')
                ->chunkById(50, function ($properties) use (&$registered, &$sold, &$remaining): void {
                    foreach ($properties as $property) {
                        $snapshot = $this->inventory->inventorySnapshot($property);
                        $registered += $snapshot['registered_acreage'];
                        $sold += $snapshot['sold_acreage'];
                        $remaining += $snapshot['remaining_acreage'];
                    }
                });

            return [
                'county' => $pair->areaCounty?->name ?? '—',
                'locality' => $pair->areaLocality?->name ?? '—',
                'registered' => round($registered, 4),
                'sold' => round($sold, 4),
                'remaining' => round($remaining, 4),
            ];
        })->sortByDesc('registered')->values()->all();
    }
}
