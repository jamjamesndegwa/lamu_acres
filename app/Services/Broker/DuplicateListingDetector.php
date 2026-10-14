<?php

namespace App\Services\Broker;

use App\Models\Property;
use Illuminate\Support\Collection;

class DuplicateListingDetector
{
    public const PARCEL_PUBLIC = 'A published listing already uses this parcel identifier.';

    public const PARCEL_PRIVATE = 'Another unpublished submission uses this parcel identifier. Staff will compare them. The other submission is not shown.';

    public const LOCATION = 'Another listing in this locality has a similar acreage. Staff will check whether it is the same land.';

    /**
     * @return array<int, string>
     */
    public function warnings(?string $parcel, ?int $localityId, mixed $acreage, ?int $ignoreId = null): array
    {
        $warnings = [];
        $parcel = $this->normalizeParcel($parcel);

        if ($parcel !== null) {
            $matches = $this->base($ignoreId)->where('parcel_identifier', $parcel)->get();

            if ($matches->contains(fn (Property $property): bool => $property->isPubliclyListed())) {
                $warnings[] = self::PARCEL_PUBLIC;
            } elseif ($matches->isNotEmpty()) {
                $warnings[] = self::PARCEL_PRIVATE;
            }
        }

        if ($localityId !== null && is_numeric($acreage) && (float) $acreage > 0) {
            $size = (float) $acreage;
            $similar = $this->base($ignoreId)
                ->where('locality_id', $localityId)
                ->whereBetween('acreage', [$size * 0.98, $size * 1.02])
                ->exists();

            if ($similar) {
                $warnings[] = self::LOCATION;
            }
        }

        return $warnings;
    }

    /**
     * @return Collection<int, Property>
     */
    public function staffMatches(Property $property): Collection
    {
        $parcel = $this->normalizeParcel($property->parcel_identifier);
        $size = is_numeric($property->acreage) ? (float) $property->acreage : 0.0;
        $hasLocation = $property->locality_id !== null && $size > 0;

        if ($parcel === null && ! $hasLocation) {
            return new Collection;
        }

        return $this->base($property->id)
            ->where(function ($query) use ($property, $parcel, $size, $hasLocation): void {
                if ($parcel !== null) {
                    $query->where('parcel_identifier', $parcel);
                }

                if (! $hasLocation) {
                    return;
                }

                $location = function ($location) use ($property, $size): void {
                    $location->where('locality_id', $property->locality_id)
                        ->whereBetween('acreage', [$size * 0.98, $size * 1.02]);
                };

                if ($parcel !== null) {
                    $query->orWhere($location);
                } else {
                    $query->where($location);
                }
            })
            ->get(['id', 'reference', 'title', 'slug', 'status', 'submitted_by', 'parcel_identifier']);
    }

    public function normalizeParcel(?string $parcel): ?string
    {
        $parcel = strtoupper((string) preg_replace('/\s+/', '', trim((string) $parcel)));

        return $parcel === '' ? null : $parcel;
    }

    private function base(?int $ignoreId)
    {
        return Property::query()->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));
    }
}
