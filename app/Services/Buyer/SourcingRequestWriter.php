<?php

namespace App\Services\Buyer;

use App\Enums\LandUse;
use App\Enums\SourcingRequestStatus;
use App\Models\BuyerTimelineEntry;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\TemplatedDeskNotification;
use App\Support\Geo\Wkt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class SourcingRequestWriter
{
    public function __construct(private SourcingMatcher $matcher) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $buyer, array $data): SourcingRequest
    {
        $request = SourcingRequest::query()->create([
            'reference' => $this->reference(),
            'buyer_id' => $buyer->id,
            ...$this->attributes($data),
            'status' => SourcingRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->syncAmenities($request, $data);
        $this->record($request, $buyer, 'Search '.$request->reference.' submitted.');

        try {
            Notification::send(
                User::permission('staff.sourcing')->get(),
                new TemplatedDeskNotification(
                    'New sourcing request',
                    'A buyer submitted '.$request->reference.'. Review and assign it in the sourcing desk.',
                    route('staff.sourcing.show', $request),
                ),
            );
        } catch (Throwable) {
            // The accepted request remains saved and visible in the sourcing queue.
        }

        $this->matcher->refresh($request);

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SourcingRequest $request, User $buyer, array $data): SourcingRequest
    {
        $request->fill($this->attributes($data));
        $request->save();
        $this->syncAmenities($request, $data);
        $this->record($request, $buyer, 'Search '.$request->reference.' updated.');
        $this->matcher->refresh($request);

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $latitude = $this->nullableFloat($data['centre_latitude'] ?? null);
        $longitude = $this->nullableFloat($data['centre_longitude'] ?? null);
        $polygon = is_array($data['polygon'] ?? null) ? array_values($data['polygon']) : [];

        return [
            'location_label' => $this->nullableString($data['location_label'] ?? null),
            'centre_latitude' => $latitude,
            'centre_longitude' => $longitude,
            'radius_km' => $this->nullableFloat($data['radius_km'] ?? null),
            'centre_wkt' => $latitude !== null && $longitude !== null ? Wkt::point($latitude, $longitude) : null,
            'boundary_wkt' => $polygon !== [] ? Wkt::polygon($polygon) : null,
            'boundary_points' => $polygon !== [] ? $polygon : null,
            'min_acreage' => $this->nullableFloat($data['min_acreage'] ?? null),
            'max_acreage' => $this->nullableFloat($data['max_acreage'] ?? null),
            'maximum_budget' => isset($data['maximum_budget']) && $data['maximum_budget'] !== '' && $data['maximum_budget'] !== null
                ? (int) $data['maximum_budget']
                : null,
            'intended_use' => isset($data['intended_use']) && $data['intended_use'] !== ''
                ? LandUse::from((string) $data['intended_use'])
                : null,
            'notes' => $this->nullableString($data['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncAmenities(SourcingRequest $request, array $data): void
    {
        $request->amenities()->delete();

        foreach (array_unique($data['mandatory'] ?? []) as $amenity) {
            $request->amenities()->create(['amenity' => $amenity, 'required' => true]);
        }

        foreach (array_unique($data['preferred'] ?? []) as $amenity) {
            $request->amenities()->create(['amenity' => $amenity, 'required' => false]);
        }
    }

    private function record(SourcingRequest $request, User $actor, string $summary): void
    {
        $request->activities()->create([
            'actor_id' => $actor->id,
            'summary' => $summary,
            'visible_to_buyer' => true,
        ]);

        BuyerTimelineEntry::record($request->buyer, $summary, [
            'sourcing_request_id' => $request->id,
        ], $actor);
    }

    private function reference(): string
    {
        do {
            $reference = 'SR-'.strtoupper(Str::random(8));
        } while (SourcingRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
