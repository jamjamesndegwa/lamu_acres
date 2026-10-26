<?php

namespace Tests\Feature\Sales;

use App\Enums\ClosingEvidenceState;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Enums\SalesStage;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Catalog\PublicListing;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\SalesPipeline;
use App\Services\Seller\SellerSalesHistory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesInventoryTraceabilityTest extends FoundationTestCase
{
    public function test_partial_completed_sale_links_one_sold_portion_to_transaction(): void
    {
        Notification::fake();

        [$property, $transaction] = $this->closePartialSale(32.0);

        $portion = $property->portions()->where('status', PropertyStatus::Sold)->sole();

        $this->assertSame($transaction->id, $portion->sales_transaction_id);
        $this->assertSame(1, $property->portions()->where('status', PropertyStatus::Sold)->count());
    }

    public function test_inventory_snapshot_is_consistent_across_presenters(): void
    {
        Notification::fake();

        [$property] = $this->closePartialSale(32.0);
        $property->refresh();

        $snapshot = app(PropertyInventory::class)->inventorySnapshot($property);
        $listing = app(PublicListing::class)->present($property);

        $this->assertEquals(40.0, $snapshot['registered_acreage']);
        $this->assertEquals(32.0, $snapshot['sold_acreage']);
        $this->assertEquals(8.0, $snapshot['remaining_acreage']);
        $this->assertEquals(8.0, $snapshot['available_acreage']);
        $this->assertEquals($snapshot['available_acreage'], $listing['inventory']['available_acreage']);
        $this->assertEquals(8.0, app(PublicListing::class)->card($property->fresh(['media']))['acres']);
    }

    public function test_two_equal_acreage_sales_map_to_distinct_portions_and_transactions(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.properties')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'minimum_acreage' => 5,
        ]);

        $first = $this->prepareForClosing($this->openSale($sales, $property, 16), $sales, 500_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $first), ['lock_version' => $first->lock_version])->assertRedirect();

        $second = $this->prepareForClosing($this->openSale($sales, $property->refresh(), 16), $sales, 600_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $second), ['lock_version' => $second->lock_version])->assertRedirect();

        $property->refresh();
        $portions = $property->portions()->where('status', PropertyStatus::Sold)->get();

        $this->assertCount(2, $portions);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $portions->pluck('sales_transaction_id')->all(),
        );
        $this->assertEquals(8.0, $property->remainingAcreage());
    }

    public function test_seller_sees_only_their_completed_sales(): void
    {
        Notification::fake();

        $owner = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $other = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();

        $mine = Property::factory()->published()->create([
            'acreage' => 10,
            'submitted_by' => $owner->id,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);
        PropertyOwner::query()->create([
            'property_id' => $mine->id,
            'user_id' => $owner->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $theirs = Property::factory()->published()->create(['acreage' => 5, 'submitted_by' => $other->id]);
        PropertyOwner::query()->create([
            'property_id' => $theirs->id,
            'user_id' => $other->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $completed = $this->prepareForClosing($this->openSale($sales, $mine, 10), $sales, 1_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $completed), ['lock_version' => $completed->lock_version])->assertRedirect();

        $foreign = $this->prepareForClosing($this->openSale($sales, $theirs, 5), $sales, 500_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $foreign), ['lock_version' => $foreign->lock_version])->assertRedirect();

        $history = app(SellerSalesHistory::class);

        $this->assertTrue($history->canView($owner, $completed->refresh()));
        $this->assertFalse($history->canView($owner, $foreign->refresh()));

        $this->actingAs($owner)
            ->get(route('seller.sales.show', $completed))
            ->assertOk()
            ->assertSee($completed->reference);

        $this->actingAs($owner)
            ->get(route('seller.sales.show', $foreign))
            ->assertForbidden();
    }

    public function test_broker_sees_deal_progress_for_introduction_or_submitted_listing(): void
    {
        Notification::fake();

        $broker = User::factory()->withRoles('broker')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 6,
            'submitted_by' => $broker->id,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);

        $transaction = $this->openSale($sales, $property, 6);
        $transaction->update(['broker_introduction_id' => null]);

        $this->actingAs($broker)
            ->get(route('broker.deals.show', $transaction))
            ->assertOk()
            ->assertSee($transaction->reference);
    }

    public function test_buyer_cannot_view_another_buyers_enquiry(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();

        $enquiry = Enquiry::factory()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'purchase_request' => true,
        ]);

        $this->actingAs($other)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertForbidden();
    }

    public function test_whole_parcel_sale_marks_property_sold(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);

        $transaction = $this->prepareForClosing($this->openSale($sales, $property, 5), $sales, 900_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Sold, $property->status);
        $this->assertSame($transaction->id, $property->sales_transaction_id);
        $this->assertFalse($property->isPubliclyListed());
    }

    public function test_pending_request_keeps_requested_acreage_when_sale_mode_changes(): void
    {
        Notification::fake();

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'submitted_by' => $seller->id,
        ]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 5_000_000,
            'requested_acreage' => 12,
            'message' => 'Interested',
        ])->assertRedirect();

        $enquiry = Enquiry::query()->sole();
        $this->assertSame(SaleMode::Partial->value, $enquiry->sale_mode_at_request);

        $terms = array_merge($property->listing_terms ?? [], ['sale_mode' => SaleMode::Whole->value]);
        $property->update(['listing_terms' => $terms]);

        $enquiry->refresh();
        $this->assertSame('12.0000', (string) $enquiry->requested_acreage);
        $this->assertSame(SaleMode::Partial->value, $enquiry->sale_mode_at_request);
        $this->assertSame(SaleMode::Whole, $property->refresh()->saleMode());
    }

    public function test_closing_rejects_stale_acreage_after_inventory_shrinks(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);

        $stale = $this->prepareForClosing($this->openSale($sales, $property, 32), $sales, 1_000_000);

        $this->closePartialSaleOnProperty($property, 32.0, $sales);

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $stale->refresh()), ['lock_version' => $stale->lock_version])
            ->assertSessionHasErrors('stage');
    }

    public function test_inventory_correction_requires_permission_reason_and_audit(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $corrector = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.inventory.correct')->create();
        $parent = Property::factory()->published()->create(['acreage' => 10]);
        $portion = Property::factory()->published()->create([
            'parent_id' => $parent->id,
            'acreage' => 4,
        ]);

        $inventory = app(PropertyInventory::class);

        $this->expectException(ValidationException::class);
        $inventory->sellPortion($staff, $portion, 'short');

        $inventory->sellPortion($corrector, $portion, 'Correcting historic sold record after title update.');

        $this->assertDatabaseHas('property_audits', [
            'property_id' => $portion->id,
            'action' => 'inventory_sale_correction',
        ]);
        $this->assertNull($portion->fresh()->sales_transaction_id);
    }

    /**
     * @return array{0: Property, 1: SalesTransaction}
     */
    private function closePartialSale(float $acreage): array
    {
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);

        $transaction = $this->prepareForClosing($this->openSale($sales, $property, $acreage), $sales, 1_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        return [$property->refresh(), $transaction->refresh()];
    }

    private function closePartialSaleOnProperty(Property $property, float $acreage, User $sales): void
    {
        $transaction = $this->prepareForClosing($this->openSale($sales, $property->refresh(), $acreage), $sales, 1_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();
    }

    private function openSale(User $sales, Property $property, float $acreage): SalesTransaction
    {
        return app(SalesPipeline::class)->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Interested.',
            ])->id,
            'acreage' => $acreage,
        ]);
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
}
