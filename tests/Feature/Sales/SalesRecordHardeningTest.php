<?php

namespace Tests\Feature\Sales;

use App\Enums\ClosingEvidenceState;
use App\Enums\CommissionStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Enums\SalesStage;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\OfferPresentation;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\CommissionDesk;
use App\Services\Sales\SalesPipeline;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesRecordHardeningTest extends FoundationTestCase
{
    public function test_buyer_purchase_request_flows_through_staff_negotiation_and_authoritative_completion(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose')->create();
        $property = Property::factory()->published()->create([
            'title' => 'Forty acre holding',
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'submitted_by' => $seller->id,
        ]);

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 12_000_000,
            'requested_acreage' => 32,
            'message' => 'Proceed after verification.',
        ])->assertRedirect();

        $enquiry = Enquiry::query()->where('purchase_request', true)->sole();
        $transaction = SalesTransaction::query()->sole();

        $this->assertSame(12_000_000, $enquiry->requested_price);
        $this->assertSame('32.0000', (string) $enquiry->requested_acreage);
        $this->assertNull($transaction->agreed_price);
        $this->assertSame('32.0000', (string) $transaction->acreage);

        $this->actingAs($sales)
            ->put(route('staff.sales.update', $transaction), [
                'lock_version' => $transaction->lock_version,
                'acreage' => 32,
            ])
            ->assertRedirect();

        $transaction->refresh();
        $this->assertSame('32.0000', (string) $transaction->acreage);
        $this->assertSame(12_000_000, $enquiry->refresh()->requested_price);

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])
            ->assertSessionHasErrors('stage');

        $transaction = $this->prepareForClosing($transaction, $sales, 11_800_000);
        $this->assertSame(11_800_000, $transaction->agreed_price);
        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])
            ->assertRedirect();

        $property->refresh();
        $transaction->refresh();

        $this->assertSame(SalesStage::Completed, $transaction->stage);
        $this->assertSame(11_800_000, $transaction->agreed_price);
        $this->assertNotNull($transaction->completed_at);
        $this->assertEquals(8.0, $property->remainingAcreage());
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(1, $property->portions()->where('status', PropertyStatus::Sold)->count());
        $portion = $property->portions()->first();
        $this->assertEquals(32.0, (float) $portion->acreage);
        $this->assertSame($transaction->id, $portion->sales_transaction_id);

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('KSh 12,000,000')
            ->assertSee('11,800,000');

        $this->actingAs($seller)
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertForbidden();
    }

    public function test_completed_sale_cannot_be_completed_again_or_repriced(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create(['acreage' => 5]);
        $pipeline = app(SalesPipeline::class);

        $transaction = $pipeline->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Interested.',
            ])->id,
            'acreage' => 5,
        ]);

        $transaction = $this->prepareForClosing($transaction, $sales, 500_000);
        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])
            ->assertRedirect();

        $transaction->refresh();
        $version = $transaction->lock_version;

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction), ['lock_version' => $version])
            ->assertSessionHasErrors('stage');

        $this->actingAs($sales)
            ->put(route('staff.sales.update', $transaction), [
                'lock_version' => $version,
                'agreed_price' => 1,
            ])
            ->assertSessionHasErrors('stage');
    }

    public function test_inventory_cannot_be_oversold_and_failed_closing_leaves_stock_unchanged(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);
        $pipeline = app(SalesPipeline::class);

        $first = $pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $property->saleInstructions()->create([
                'seller_id' => User::factory()->withRoles('seller')->create()->id,
                'kind' => 'general',
                'body' => 'Sell in portions.',
            ])->id,
            'acreage' => 32,
        ]);
        $first = $this->prepareForClosing($first, $sales, 9_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $first), ['lock_version' => $first->lock_version])->assertRedirect();

        $this->assertEquals(8.0, $property->refresh()->remainingAcreage());

        $this->expectException(ValidationException::class);
        $pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $property->saleInstructions()->first()->id,
            'acreage' => 10,
        ]);
    }

    public function test_whole_property_closing_removes_it_from_available_listings(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create(['acreage' => 8]);
        $pipeline = app(SalesPipeline::class);

        $transaction = $pipeline->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Buy all.',
            ])->id,
            'acreage' => 8,
        ]);

        $transaction = $this->prepareForClosing($transaction, $sales, 4_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Sold, $property->status);
        $this->assertFalse($property->isPubliclyListed());
    }

    public function test_direct_status_and_portion_sale_bypasses_are_blocked_without_authority(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $parent = Property::factory()->published()->create([
            'title' => 'Bypass parent',
            'acreage' => 10,
        ]);

        $this->actingAs($staff)->put(route('staff.properties.transition', $parent), [
            'status' => PropertyStatus::Sold->value,
        ])->assertSessionHasErrors('status');

        $portion = Property::factory()->published()->create([
            'title' => 'Bypass portion',
            'parent_id' => $parent->id,
            'acreage' => 4,
        ]);

        $this->actingAs($staff)->post(route('staff.properties.sell-portion', $portion))->assertSessionHasErrors('portion');
        $this->assertSame(PropertyStatus::Published, $portion->fresh()->status);
    }

    public function test_commission_earns_once_from_agreed_price_not_listing_ask(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $property = Property::factory()->published()->create(['acreage' => 10, 'price_amount' => 20_000_000]);
        $pipeline = app(SalesPipeline::class);
        $commissions = app(CommissionDesk::class);

        $transaction = $pipeline->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Offer.',
            ])->id,
            'acreage' => 10,
        ]);

        $agreement = $commissions->propose($transaction, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 10,
            'funding_party' => 'seller',
            'protection_ends_at' => now()->addYear(),
        ]);
        $commissions->accept($agreement, $sales);

        $transaction = $this->prepareForClosing($transaction, $sales, 8_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $agreement->refresh();
        $this->assertSame(CommissionStatus::Earned, $agreement->status);
        $this->assertSame(800_000, $agreement->earned_amount);
        $this->assertSame(CommissionStatus::Earned, $agreement->refresh()->status);

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction->refresh()), ['lock_version' => $transaction->lock_version])
            ->assertSessionHasErrors('stage');
    }

    public function test_seller_offer_response_stays_on_presentation_and_does_not_complete_sale(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->published()->create(['submitted_by' => $seller->id]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);
        $buyer = User::factory()->withRoles('buyer')->create();
        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
            'message' => 'Buyer offer.',
        ]);
        $presentation = OfferPresentation::query()->create([
            'offer_id' => $offer->id,
            'property_id' => $property->id,
            'presented_by' => User::factory()->withRoles('staff')->withPermissions('staff.sales')->create()->id,
            'amount' => 1_000_000,
            'summary' => 'Presented offer.',
        ]);

        $this->actingAs($seller)->post(route('seller.offers.respond', $presentation), [
            'seller_response' => 'accept',
        ])->assertRedirect();

        $this->assertSame('accept', $presentation->refresh()->seller_response);
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);
        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_partial_close_caps_stale_offered_acreage_for_pricing(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'price_basis' => PriceBasis::PerAcre,
            'price_rate' => 100_000,
            'price_amount' => 4_000_000,
        ]);

        $pipeline = app(SalesPipeline::class);
        $transaction = $pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $property->saleInstructions()->create([
                'seller_id' => User::factory()->withRoles('seller')->create()->id,
                'kind' => 'general',
                'body' => 'Sell part.',
            ])->id,
            'acreage' => 32,
        ]);

        $transaction = $this->prepareForClosing($transaction, $sales, 3_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $property->refresh();
        $this->assertEquals(8.0, (float) $property->offered_acreage);
        $this->assertSame(800_000, $property->price_amount);
    }

    public function test_concurrent_closing_attempts_cannot_oversell_the_same_parcel(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);
        $pipeline = app(SalesPipeline::class);

        $left = $this->prepareForClosing($pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $property->saleInstructions()->create([
                'seller_id' => User::factory()->withRoles('seller')->create()->id,
                'kind' => 'general',
                'body' => 'A',
            ])->id,
            'acreage' => 32,
        ]), $sales, 1_000_000);

        $right = $this->prepareForClosing($pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $property->saleInstructions()->first()->id,
            'acreage' => 32,
        ]), $sales, 1_100_000);

        $this->actingAs($sales)->post(route('staff.sales.complete', $left), ['lock_version' => $left->lock_version])->assertRedirect();
        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $right), ['lock_version' => $right->lock_version])
            ->assertSessionHasErrors('acreage');
        $this->assertEquals(8.0, $property->refresh()->remainingAcreage());
    }

    private function prepareForClosing(SalesTransaction $transaction, User $sales, int $price = 1_000_000): SalesTransaction
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
