<?php

namespace App\Support\Reporting;

use App\Models\Property;
use App\Models\SalesTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReportingFilters
{
    public function __construct(
        public readonly ?int $countyId = null,
        public readonly ?int $localityId = null,
        public readonly ?int $assignedTo = null,
        public readonly ?int $brokerId = null,
        public readonly ?int $propertyId = null,
        public readonly ?string $stage = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'county_id' => ['nullable', 'integer', Rule::exists('counties', 'id')],
            'locality_id' => ['nullable', 'integer', Rule::exists('localities', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'broker_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')],
            'stage' => ['nullable', 'string'],
        ]);

        return new self(
            isset($data['county_id']) ? (int) $data['county_id'] : null,
            isset($data['locality_id']) ? (int) $data['locality_id'] : null,
            isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            isset($data['broker_id']) ? (int) $data['broker_id'] : null,
            isset($data['property_id']) ? (int) $data['property_id'] : null,
            $data['stage'] ?? null,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function query(): array
    {
        return array_filter([
            'county_id' => $this->countyId,
            'locality_id' => $this->localityId,
            'assigned_to' => $this->assignedTo,
            'broker_id' => $this->brokerId,
            'property_id' => $this->propertyId,
            'stage' => $this->stage,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  Builder<SalesTransaction>  $query
     * @return Builder<SalesTransaction>
     */
    public function applyToSales(Builder $query): Builder
    {
        if ($this->propertyId !== null) {
            $query->where('property_id', $this->propertyId);
        }

        if ($this->assignedTo !== null) {
            $query->where('assigned_to', $this->assignedTo);
        }

        if ($this->brokerId !== null) {
            $query->where(function (Builder $builder): void {
                $builder->whereHas('introduction', fn ($intro) => $intro->where('broker_id', $this->brokerId))
                    ->orWhereHas('property', fn ($property) => $property->where('submitted_by', $this->brokerId));
            });
        }

        if ($this->stage !== null) {
            $query->where('stage', $this->stage);
        }

        if ($this->countyId !== null || $this->localityId !== null) {
            $query->whereHas('property', function (Builder $property): void {
                if ($this->countyId !== null) {
                    $property->where('county_id', $this->countyId);
                }

                if ($this->localityId !== null) {
                    $property->where('locality_id', $this->localityId);
                }
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Property>  $query
     * @return Builder<Property>
     */
    public function applyToProperties(Builder $query): Builder
    {
        if ($this->propertyId !== null) {
            $query->whereKey($this->propertyId);
        }

        if ($this->countyId !== null) {
            $query->where('county_id', $this->countyId);
        }

        if ($this->localityId !== null) {
            $query->where('locality_id', $this->localityId);
        }

        return $query;
    }
}
