<?php

namespace Tests\Feature\Inventory;

use App\Enums\ClosingEvidenceState;
use App\Enums\ListingPurpose;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SalesOrigin;
use App\Enums\SalesStage;
use App\Enums\TransactionStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyTransaction;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\ClosingDesk;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class LeaseLifecycleTest extends FoundationTestCase
{
    public function test_staff_can_record_a_lease_for_a_published_lease_listing(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->createLocality();

        $property = Property::factory()->published()->listedIn($locality)->lease(PriceBasis::ParcelMonth)->create([
            'title' => 'Shela Beach House Lease',
            'acreage' => 2.5,
            'offered_acreage' => 2.5,
            'price_rate' => 120_000,
            'price_amount' => 120_000,
        ]);

        $response = $this->actingAs($staff)->post(route('staff.properties.leases.store', $property), [
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addYears(2)->toDateString(),
            'acreage' => 2.5,
            'amount' => 120_000,
            'terms' => '24-month lease, rent payable monthly in advance.',
            'statement' => 'Initial 2-year lease agreement signed.',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Leased, $property->status);
        $this->assertSame(PropertyAvailability::Unavailable, $property->availability);

        $lease = PropertyTransaction::query()->where('property_id', $property->id)->first();
        $this->assertNotNull($lease);
        $this->assertSame(ListingPurpose::Lease, $lease->kind);
        $this->assertSame(TransactionStatus::Completed, $lease->status);
        $this->assertSame(120_000, $lease->amount);
        $this->assertEquals(2.5, (float) $lease->acreage);
        $this->assertSame($staff->id, $lease->confirmed_by);
        $this->assertNotNull($lease->confirmed_at);
        $this->assertNull($lease->ended_at);
        $this->assertTrue($lease->isActiveLease());
    }

    public function test_staff_can_end_an_active_lease_and_property_returns_to_review(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->createLocality();

        $property = Property::factory()->published()->listedIn($locality)->lease()->create([
            'status' => PropertyStatus::Leased,
            'availability' => PropertyAvailability::Unavailable,
            'acreage' => 5,
        ]);

        $lease = $property->transactions()->create([
            'reference' => 'TX-TESTLEASE',
            'kind' => ListingPurpose::Lease,
            'status' => TransactionStatus::Completed,
            'amount' => 80_000,
            'acreage' => 5,
            'starts_on' => now()->subYears(2)->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'confirmed_by' => $staff->id,
            'confirmed_at' => now()->subYears(2),
            'statement' => 'Active agricultural lease.',
        ]);

        $response = $this->actingAs($staff)->put(route('staff.leases.end', $lease), [
            'reason' => 'Tenant surrendered tenancy early by mutual consent.',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $lease->refresh();
        $this->assertNotNull($lease->ended_at);
        $this->assertFalse($lease->isActiveLease());
        $this->assertStringContainsString('Tenant surrendered tenancy early by mutual consent.', (string) $lease->statement);

        $property->refresh();
        $this->assertSame(PropertyStatus::Review, $property->status);
    }

    public function test_record_lease_validates_acreage_and_purpose(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->createLocality();

        // 1. Sale listing rejects recordLease
        $saleProperty = Property::factory()->published()->listedIn($locality)->create([
            'purpose' => ListingPurpose::Sale,
            'acreage' => 10,
        ]);

        $this->actingAs($staff)->post(route('staff.properties.leases.store', $saleProperty), [
            'starts_on' => now()->toDateString(),
            'acreage' => 5,
            'amount' => 50_000,
        ])->assertSessionHasErrors('lease');

        // 2. Lease listing rejects acreage exceeding offered acreage
        $leaseProperty = Property::factory()->published()->listedIn($locality)->lease()->create([
            'acreage' => 4,
            'offered_acreage' => 4,
        ]);

        $this->actingAs($staff)->post(route('staff.properties.leases.store', $leaseProperty), [
            'starts_on' => now()->toDateString(),
            'acreage' => 5,
            'amount' => 50_000,
        ])->assertSessionHasErrors('acreage');
    }

    public function test_purpose_aware_transition_rules(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $inventory = app(PropertyInventory::class);
        $locality = $this->createLocality();

        $leaseProperty = Property::factory()->published()->listedIn($locality)->lease()->create();
        $saleProperty = Property::factory()->published()->listedIn($locality)->create([
            'purpose' => ListingPurpose::Sale,
        ]);

        // Lease cannot transition to Sold
        $this->expectException(ValidationException::class);
        $inventory->transition($staff, $leaseProperty, PropertyStatus::Sold);

        // Sale cannot transition to Leased
        $this->expectException(ValidationException::class);
        $inventory->transition($staff, $saleProperty, PropertyStatus::Leased);
    }

    public function test_closing_desk_refuses_sale_completion_on_lease_properties(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $locality = $this->createLocality();

        $leaseProperty = Property::factory()->published()->listedIn($locality)->lease()->create([
            'acreage' => 5,
        ]);

        $buyer = User::factory()->withRoles('buyer')->create();
        $salesTransaction = SalesTransaction::query()->create([
            'reference' => 'STX-TEST-001',
            'origin' => SalesOrigin::Enquiry,
            'property_id' => $leaseProperty->id,
            'buyer_id' => $buyer->id,
            'assigned_to' => $staff->id,
            'stage' => SalesStage::CompletionPending,
            'agreed_price' => 5_000_000,
            'acreage' => 5,
            'lock_version' => 1,
        ]);

        // Authorize closing review
        $salesTransaction->closingReviews()->create([
            'reviewer_id' => $staff->id,
            'decision' => 'authorized',
            'reason' => 'Authorized for closing.',
            'reviewed_at' => now(),
        ]);

        // Seed and accept all required evidence
        $closingDesk = app(ClosingDesk::class);
        foreach (ClosingDesk::EVIDENCE as $code => $label) {
            $closingDesk->evidence($salesTransaction, $staff, $code, ClosingEvidenceState::Accepted, 'Accepted proof.');
        }

        $this->expectException(ValidationException::class);
        $closingDesk->complete($salesTransaction, $staff, 1);
    }

    public function test_exclusive_whole_parcel_leases_block_overlapping_active_leases(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $inventory = app(PropertyInventory::class);
        $locality = $this->createLocality();

        $property = Property::factory()->published()->listedIn($locality)->lease()->create([
            'acreage' => 4,
            'offered_acreage' => 4,
        ]);

        $property->transactions()->create([
            'reference' => 'TX-PARTIAL',
            'kind' => ListingPurpose::Lease,
            'status' => TransactionStatus::Completed,
            'amount' => 50_000,
            'acreage' => 2,
            'starts_on' => now()->toDateString(),
            'confirmed_by' => $staff->id,
            'confirmed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $inventory->recordLease($staff, $property, [
            'starts_on' => now()->toDateString(),
            'acreage' => 4,
            'amount' => 80_000,
        ]);
    }

    private function createLocality(): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);

        return Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => 'shela'],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );
    }
}
