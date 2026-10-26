<?php

namespace Tests\Feature\Sales;

use App\Enums\ClosingEvidenceState;
use App\Enums\ListingPurpose;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Enums\SalesStage;
use App\Models\County;
use App\Models\Enquiry;
use App\Models\Locality;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\SalesPipeline;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class SaleModeEnforcementTest extends FoundationTestCase
{
    public function test_seller_can_create_whole_parcel_and_partial_sale_listings(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $locality = $this->locality();

        $this->actingAs($seller)->post(route('seller.properties.store'), $this->sellerPayload($locality, [
            'title' => 'Whole ridge',
            'sale_mode' => SaleMode::Whole->value,
            'acreage' => 12,
        ]))->assertSessionHasNoErrors();

        $whole = Property::query()->where('title', 'Whole ridge')->firstOrFail();
        $this->assertSame(SaleMode::Whole, $whole->saleMode());
        $this->assertNull($whole->minimum_acreage);

        $this->actingAs($seller)->post(route('seller.properties.store'), $this->sellerPayload($locality, [
            'title' => 'Partial farm',
            'sale_mode' => SaleMode::Partial->value,
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
        ]))->assertSessionHasNoErrors();

        $partial = Property::query()->where('title', 'Partial farm')->firstOrFail();
        $this->assertSame(SaleMode::Partial, $partial->saleMode());
        $this->assertEquals(5.0, (float) $partial->minimum_acreage);
    }

    public function test_whole_only_purchase_request_for_smaller_acreage_is_rejected(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 4_000_000,
                'requested_acreage' => 10,
            ])
            ->assertSessionHasErrors('requested_acreage');

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_partial_purchase_within_limits_succeeds_and_enforces_minimum(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 8,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 800_000,
                'requested_acreage' => 3,
            ])
            ->assertSessionHasErrors('requested_acreage');

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 800_000,
                'requested_acreage' => 6,
            ])
            ->assertRedirect();

        $this->assertSame('6.0000', (string) SalesTransaction::query()->sole()->acreage);
    }

    public function test_closing_respects_sale_mode_and_updates_public_stock(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'price_basis' => PriceBasis::PerAcre,
            'price_rate' => 100_000,
            'price_amount' => 4_000_000,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);

        $pipeline = app(SalesPipeline::class);
        $transaction = $pipeline->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Buy six acres.',
            ])->id,
            'acreage' => 32,
        ]);

        $transaction = $this->prepareForClosing($transaction, $sales, 3_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $property->refresh();
        $this->assertEquals(8.0, $property->remainingAcreage());
        $this->assertEquals(8.0, (float) $property->offered_acreage);

        $this->actingAs(User::factory()->withRoles('buyer')->create())
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 1_000_000,
                'requested_acreage' => 10,
            ])
            ->assertSessionHasErrors('requested_acreage');
    }

    public function test_whole_only_listing_cannot_close_partial_transaction(): void
    {
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);

        $pipeline = app(SalesPipeline::class);

        $this->expectException(ValidationException::class);
        $pipeline->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Partial attempt.',
            ])->id,
            'acreage' => 32,
        ]);
    }

    public function test_pre_created_portion_is_reused_instead_of_duplicating_sold_inventory(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.sales')->create();
        $parent = Property::factory()->published()->create([
            'acreage' => 40,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'minimum_acreage' => 5,
            'offered_acreage' => 40,
        ]);
        $child = Property::factory()->published()->create([
            'parent_id' => $parent->id,
            'acreage' => 32,
            'title' => 'North 32',
        ]);

        $inventory = app(PropertyInventory::class);
        $inventory->closeSale($staff, $parent, 32.0, 2_000_000);

        $this->assertSame(1, $parent->portions()->count());
        $this->assertSame(PropertyStatus::Sold, $child->fresh()->status);
    }

    private function prepareForClosing(SalesTransaction $transaction, User $sales, int $price): SalesTransaction
    {
        $pipeline = app(SalesPipeline::class);
        $closing = app(ClosingDesk::class);

        foreach ([
            SalesStage::Viewing,
            SalesStage::Offer,
            SalesStage::Negotiation,
            SalesStage::OfferAccepted,
            SalesStage::Contracting,
            SalesStage::CompletionPending,
        ] as $stage) {
            $transaction = $pipeline->advance($transaction, $stage, $sales, $transaction->lock_version);
        }

        $transaction = $pipeline->agreePrice($transaction, $price, $sales, $transaction->lock_version);
        $closing->review($transaction, $sales, 'authorized', 'Authorized for test completion.');
        foreach (array_keys(ClosingDesk::EVIDENCE) as $code) {
            $closing->evidence($transaction, $sales, $code, ClosingEvidenceState::Accepted, 'Accepted.');
        }

        return $transaction->refresh();
    }

    private function locality(): Locality
    {
        $county = County::query()->create([
            'name' => 'Lamu',
            'slug' => 'lamu-sale-mode',
            'is_active' => true,
        ]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Mokowe',
            'slug' => 'mokowe-sale-mode',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sellerPayload(Locality $locality, array $overrides = []): array
    {
        return array_merge([
            'purpose' => ListingPurpose::Sale->value,
            'relationship' => 'owner',
            'summary' => 'Summary text for listing.',
            'description' => 'Description text for listing.',
            'property_type' => 'agricultural',
            'land_use' => 'agricultural',
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'parcel_identifier' => 'LAMU/TEST/100',
            'parcel_reference_type' => 'title_number',
            'tenure' => 'freehold',
            'ownership_structure' => 'individual',
            'sale' => [
                'price_basis' => PriceBasis::Total->value,
                'amount' => 1_000_000,
            ],
        ], $overrides);
    }
}
