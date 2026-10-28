<?php

namespace Tests\Feature\Buyer;

use App\Enums\SalesOutcome;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SalesOfferEntry;
use App\Models\SalesTransaction;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\EnquirySubmittedNotification;
use App\Services\Sales\SalesPipeline;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class PurchaseRequestJourneyTest extends FoundationTestCase
{
    public function test_buyer_can_enquire_view_and_request_a_viewing_on_available_land(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create(['title' => 'Shela ridge']);

        $this->actingAs($buyer)
            ->post(route('land.enquiries.store', $property), [
                'message' => 'Please confirm road access.',
            ])
            ->assertRedirect(route('buyer.enquiries.index'));

        $this->actingAs($buyer)
            ->post(route('land.visits.store', $property), [
                'requested_for' => now()->addDays(3)->toDateString(),
                'message' => 'Morning visit preferred.',
            ])
            ->assertRedirect();

        $enquiry = Enquiry::query()->sole();
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertFalse($enquiry->purchase_request);

        Notification::assertSentTo($desk, EnquirySubmittedNotification::class);
    }

    public function test_purchase_interest_links_to_property_selling_party_and_staff_sales_pipeline(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.buyers')->create();
        $property = Property::factory()->published()->create([
            'title' => 'Manda holding',
            'submitted_by' => $seller->id,
        ]);

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 3_500_000,
                'message' => 'Ready to move quickly after verification.',
            ])
            ->assertRedirect();

        $enquiry = Enquiry::query()->where('purchase_request', true)->sole();
        $transaction = SalesTransaction::query()->sole();

        $this->assertSame($seller->id, $property->submitted_by);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertSame($transaction->id, $enquiry->salesTransaction->id);

        $this->actingAs($sales)
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Purchase request')
            ->assertSee($transaction->reference);

        $this->actingAs($sales)
            ->get(route('staff.sales.index'))
            ->assertOk()
            ->assertSee('Purchase request')
            ->assertSee('Manda holding');
    }

    public function test_purchase_interest_reuses_an_open_sales_case_without_creating_a_duplicate(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();
        $sourcing = SourcingRequest::query()->create([
            'reference' => 'SR-REUSE-01',
            'buyer_id' => $buyer->id,
            'status' => 'submitted',
            'location_label' => 'Shela',
            'centre_latitude' => -2.27,
            'centre_longitude' => 40.9,
            'radius_km' => 5,
            'centre_wkt' => 'POINT(40.9 -2.27)',
            'submitted_at' => now(),
        ]);

        $this->actingAs($sales)->post(route('staff.sales.store'), [
            'origin' => 'sourcing',
            'property_id' => $property->id,
            'sourcing_request_id' => $sourcing->id,
            'assigned_to' => $sales->id,
        ])->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 2_100_000,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('sales_transactions', 1);
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertSame(1, SalesOfferEntry::query()->count());

        $transaction = SalesTransaction::query()->sole();
        $this->assertNotNull($transaction->enquiry_id);
        $this->assertTrue($transaction->enquiry->purchase_request);
    }

    public function test_buyers_can_only_view_their_own_requests_and_partners_cannot_open_staff_enquiries(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 1_000_000,
        ]);

        $enquiry = Enquiry::query()->sole();

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Purchase request progress');

        $this->actingAs($other)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertForbidden();

        $this->actingAs($seller)
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertForbidden();
    }

    public function test_unavailable_property_cannot_accept_a_new_purchase_interest(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->unavailable()->create();

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 1_000_000,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('enquiries', 0);
        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_declining_a_sales_case_preserves_the_buyer_purchase_history(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 1_250_000,
        ]);

        $enquiry = Enquiry::query()->sole();
        $transaction = SalesTransaction::query()->sole();

        app(SalesPipeline::class)->outcome(
            $transaction,
            SalesOutcome::Declined,
            $sales,
            (int) $transaction->lock_version,
            'Seller declined the proposed price.',
        );

        $transaction->refresh();
        $this->assertSame(SalesOutcome::Declined, $transaction->outcome);
        $this->assertTrue($enquiry->purchase_request);

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Declined')
            ->assertSee('KSh 1,250,000');
    }

    public function test_public_listing_shows_the_buy_land_action_for_authenticated_buyers(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create(['title' => 'Coastal parcel']);

        $this->actingAs($buyer)
            ->get($property->publicUrl())
            ->assertOk()
            ->assertSee("I'm interested in buying", false);
    }

    public function test_buyer_can_withdraw_active_purchase_interest_and_history_is_preserved(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 2_400_000,
        ])->assertRedirect();

        $enquiry = Enquiry::query()->sole();
        $transaction = SalesTransaction::query()->sole();

        $this->actingAs($buyer)
            ->post(route('buyer.enquiries.withdraw', $enquiry), [
                'note' => 'Timing no longer works.',
            ])
            ->assertRedirect(route('buyer.enquiries.show', $enquiry))
            ->assertSessionHas('status');

        $enquiry->refresh();
        $transaction->refresh();

        $this->assertSame(SalesOutcome::Withdrawn, $transaction->outcome);
        $this->assertTrue($enquiry->purchase_request);
        $this->assertNotNull($enquiry->closed_at);

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Withdrawn', false)
            ->assertSee('KSh 2,400,000', false);

        $this->actingAs($other)
            ->post(route('buyer.enquiries.withdraw', $enquiry))
            ->assertForbidden();
    }

    public function test_buyer_can_open_a_new_purchase_request_after_withdrawing(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 1_000_000,
        ])->assertRedirect();

        $enquiry = Enquiry::query()->sole();
        $this->actingAs($buyer)->post(route('buyer.enquiries.withdraw', $enquiry))->assertRedirect();

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 1_050_000,
        ])->assertRedirect();

        $this->assertSame(2, Enquiry::query()->where('purchase_request', true)->count());
        $this->assertSame(2, SalesTransaction::query()->count());
    }
}
