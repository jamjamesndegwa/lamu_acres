<?php

namespace App\Services\Inventory;

use App\Enums\ListingPurpose;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\TransactionStatus;
use App\Events\PropertyInventoryChanged;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\Verification\VerificationOperations;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyInventory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Property
    {
        $property = new Property;
        $this->fill($property, $data);
        $property->status = PropertyStatus::Draft;
        $property->availability = PropertyAvailability::Available;
        $property->save();
        $this->recordPrice($property, $actor, null);
        $this->recordAvailability($property, $actor);

        event(new PropertyInventoryChanged($property, $actor->id, 'created', null, PropertyStatus::Draft->value, [
            'reference' => $property->reference,
        ]));

        return $property;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Property $property, array $data): Property
    {
        $previousPrice = $property->price_amount;
        $previousAvailability = $property->availability?->value;
        $this->fill($property, $data);
        $property->save();
        $this->recordPrice($property, $actor, $previousPrice);

        if ($previousAvailability !== $property->availability?->value) {
            $this->recordAvailability($property, $actor);
        }

        event(new PropertyInventoryChanged($property, $actor->id, 'updated', $property->status->value, $property->status->value, [
            'title' => $property->title,
        ]));

        return $property;
    }

    public function transition(User $actor, Property $property, PropertyStatus $status): Property
    {
        $from = $property->status;

        if (! in_array($status, $property->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => in_array($status, $from->transitions(), true)
                    ? 'A '.strtolower($property->purpose?->label() ?? 'listing').' listing cannot be marked '.strtolower($status->label()).'.'
                    : 'That status change is not available from '.$from->label().'.',
            ]);
        }

        if ($status === PropertyStatus::Published) {
            app(VerificationOperations::class)->assertClear($property);
        }

        $this->applyStatus($property, $status);
        $property->save();
        $this->recordAvailability($property, $actor);

        event(new PropertyInventoryChanged($property, $actor->id, 'status_changed', $from->value, $status->value));

        return $property;
    }

    /**
     * The single publication path: allowed transition plus the full verification and listing gate.
     */
    public function publish(User $actor, Property $property): Property
    {
        return $this->transition($actor, $property, PropertyStatus::Published);
    }

    /**
     * Record a staff-confirmed lease and take the land off the market. This does not
     * execute or register the lease; that stays with staff and legal advisers.
     *
     * @param  array{starts_on: string, ends_on?: string|null, acreage: float|string, amount: int|string, terms?: string|null, statement?: string|null}  $data
     */
    public function recordLease(User $actor, Property $property, array $data): PropertyTransaction
    {
        if (! $property->isLease()) {
            throw ValidationException::withMessages([
                'lease' => 'Only a lease listing can have a lease recorded. Sales close through the sales desk.',
            ]);
        }

        if (! in_array(PropertyStatus::Leased, $property->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'lease' => 'A lease can only be recorded for a published or reserved listing.',
            ]);
        }

        $acreage = round((float) $data['acreage'], 4);

        if ($acreage <= 0 || $acreage > round($property->offeredAcreage(), 4) + 0.0001) {
            throw ValidationException::withMessages([
                'acreage' => 'The leased area cannot be larger than the acreage offered.',
            ]);
        }

        return DB::transaction(function () use ($actor, $property, $data, $acreage): PropertyTransaction {
            $lease = $property->transactions()->create([
                'reference' => 'TX-'.strtoupper(Str::random(8)),
                'kind' => ListingPurpose::Lease,
                'status' => TransactionStatus::Completed,
                'amount' => (int) $data['amount'],
                'acreage' => $acreage,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'terms' => $data['terms'] ?? null,
                'statement' => $data['statement'] ?? 'Lease confirmed by staff.',
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            $this->transition($actor, $property, PropertyStatus::Leased);

            return $lease;
        });
    }

    /**
     * Record that a lease has ended. The land returns to review so staff confirm it
     * before it is advertised again.
     */
    public function endLease(User $actor, PropertyTransaction $lease, string $reason): PropertyTransaction
    {
        if (! $lease->isActiveLease()) {
            throw ValidationException::withMessages(['lease' => 'This lease has already ended.']);
        }

        if (strlen(trim($reason)) < 8) {
            throw ValidationException::withMessages(['reason' => 'Record why the lease ended.']);
        }

        return DB::transaction(function () use ($actor, $lease, $reason): PropertyTransaction {
            $lease->ended_at = now();
            $lease->statement = trim(($lease->statement ? $lease->statement."\n" : '').'Lease ended: '.trim($reason));
            $lease->save();

            $property = $lease->property;

            if ($property->status === PropertyStatus::Leased) {
                $this->transition($actor, $property, PropertyStatus::Review);
            }

            return $lease;
        });
    }

    public function sellPortion(User $actor, Property $portion): Property
    {
        if ($portion->parent_id === null) {
            throw ValidationException::withMessages([
                'portion' => 'Only a subdivided portion can be sold on its own.',
            ]);
        }

        $this->transition($actor, $portion, PropertyStatus::Sold);

        $parent = $portion->parent()->first();

        if ($parent !== null && $parent->remainingAcreage() <= 0 && in_array(PropertyStatus::Sold, $parent->allowedTransitions(), true)) {
            $this->transition($actor, $parent, PropertyStatus::Sold);
        }

        return $portion->refresh();
    }

    public function closeSale(User $actor, Property $property, float $acreage, ?int $price = null): Property
    {
        $locked = Property::query()->whereKey($property->id)->lockForUpdate()->firstOrFail();
        $requested = round($acreage, 4);
        $remaining = round($locked->remainingAcreage(), 4);

        if ($requested <= 0 || $requested > $remaining + 0.0001) {
            throw ValidationException::withMessages([
                'acreage' => 'The sale acreage is larger than the land still held.',
            ]);
        }

        if (abs($requested - $remaining) < 0.0001) {
            if ($locked->parent_id !== null) {
                return $this->sellPortion($actor, $locked);
            }

            return $this->transition($actor, $locked, PropertyStatus::Sold);
        }

        $portion = $locked->replicate(['published_at']);
        $portion->reference = $this->reference();
        $portion->slug = Str::slug($locked->title).'-sold-'.Str::lower(Str::random(5));
        $portion->parent_id = $locked->id;
        $portion->acreage = $requested;
        $portion->price_amount = $price ?? $locked->price_amount;
        $portion->status = PropertyStatus::Sold;
        $portion->availability = PropertyAvailability::Unavailable;
        $portion->published_at = null;
        $portion->portion_label = 'Closed sale';
        $portion->title = $locked->title.' portion';
        $portion->save();
        $this->recordAvailability($portion, $actor);

        event(new PropertyInventoryChanged($portion, $actor->id, 'portion_sold', null, PropertyStatus::Sold->value, [
            'parent' => $locked->reference,
            'acreage' => $requested,
        ]));

        return $portion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Property $property, array $data): void
    {
        $locality = Locality::query()->with('county')->findOrFail($data['locality_id']);
        $parent = isset($data['parent_id']) ? Property::query()->find($data['parent_id']) : $property->parent;

        if ((int) $locality->county_id !== (int) $data['county_id']) {
            throw ValidationException::withMessages([
                'locality_id' => 'Choose a locality in the selected county.',
            ]);
        }

        if ($parent instanceof Property) {
            $requested = (float) $data['acreage'];
            $already = $property->exists ? (float) $property->acreage : 0;
            $available = $parent->remainingAcreage() + $already;

            if ($requested > $available + 0.0001) {
                throw ValidationException::withMessages([
                    'acreage' => 'That portion is larger than the acreage still held by the parent parcel.',
                ]);
            }
        }

        $title = (string) $data['title'];
        $basis = PriceBasis::tryFrom((string) ($data['price_basis'] ?? '')) ?? $property->price_basis ?? PriceBasis::Total;
        $offered = filled($data['offered_acreage'] ?? null) ? (float) $data['offered_acreage'] : null;
        $rate = filled($data['price_rate'] ?? null) ? (int) $data['price_rate'] : null;
        $priceAmount = $basis->isPerAcre() && $rate !== null
            ? Property::computePriceAmount($basis, $rate, $offered ?? (float) $data['acreage'])
            : (filled($data['price_amount'] ?? null) ? (int) $data['price_amount'] : $rate);

        $property->fill([
            'reference' => $data['reference'] ?? $property->reference ?? $this->reference(),
            'slug' => $property->slug ?? Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title' => $title,
            'description' => $data['description'],
            'summary' => $data['summary'] ?? $property->summary,
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'county' => $locality->county->name,
            'locality' => $locality->name,
            'property_type' => $data['property_type'],
            'land_use' => $data['land_use'],
            'purpose' => $data['purpose'],
            'acreage' => $data['acreage'],
            'offered_acreage' => $offered,
            'price_basis' => $basis,
            'price_rate' => $basis->isPerAcre() ? $rate : ($rate ?? $priceAmount),
            'price_amount' => $priceAmount,
            'price_currency' => 'KES',
            'public_latitude' => $data['public_latitude'] ?? null,
            'public_longitude' => $data['public_longitude'] ?? null,
            'marketing_role' => $data['marketing_role'],
            'represented_by' => $data['represented_by'] ?? null,
            'parent_id' => $parent?->id,
            'portion_label' => $data['portion_label'] ?? null,
        ]);

        $property->restricted_latitude = $data['restricted_latitude'] ?? null;
        $property->restricted_longitude = $data['restricted_longitude'] ?? null;
    }

    private function applyStatus(Property $property, PropertyStatus $status): void
    {
        $property->status = $status;

        if ($status === PropertyStatus::Published) {
            $property->availability = PropertyAvailability::Available;
            $property->published_at ??= now();
        }

        if (in_array($status, [
            PropertyStatus::Reserved,
            PropertyStatus::Sold,
            PropertyStatus::Leased,
            PropertyStatus::Withdrawn,
            PropertyStatus::Suspended,
            PropertyStatus::Archived,
        ], true)) {
            $property->availability = PropertyAvailability::Unavailable;
        }
    }

    private function recordPrice(Property $property, User $actor, mixed $previous): void
    {
        if ($property->price_amount === null) {
            return;
        }

        if ($previous !== null && (int) $previous === (int) $property->price_amount) {
            return;
        }

        $property->priceChanges()->create([
            'actor_id' => $actor->id,
            'price_amount' => (int) $property->price_amount,
            'price_currency' => $property->price_currency ?: 'KES',
        ]);
    }

    private function recordAvailability(Property $property, User $actor): void
    {
        $property->availabilityEvents()->create([
            'actor_id' => $actor->id,
            'status' => $property->status->value,
            'availability' => ($property->availability ?? PropertyAvailability::Available)->value,
        ]);
    }

    private function reference(): string
    {
        do {
            $reference = 'LA-'.strtoupper(Str::random(8));
        } while (Property::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
