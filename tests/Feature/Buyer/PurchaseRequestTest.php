<?php

namespace Tests\Feature\Buyer;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SalesOfferEntry;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class PurchaseRequestTest extends FoundationTestCase
{
    public function test_buyer_purchase_request_opens_a_sales_case_and_is_visible_in_buyer_progress(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $salesAgent = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create([
            'title' => 'Manda Coast Plot',
            'acreage' => 8,
            'offered_acreage' => 6,
        ]);

        $response = $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 4_200_000,
            'requested_acreage' => 2.5,
            'message' => 'I can proceed after a site visit.',
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $enquiry = Enquiry::query()->sole();
        $transaction = SalesTransaction::query()->sole();

        $response->assertRedirect(route('buyer.enquiries.show', $enquiry));
        $this->assertDatabaseHas('enquiries', [
            'id' => $enquiry->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'purchase_request' => true,
            'requested_price' => 4_200_000,
            'requested_acreage' => '2.5000',
        ]);
        $this->assertDatabaseHas('sales_transactions', [
            'id' => $transaction->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'enquiry_id' => $enquiry->id,
            'assigned_to' => $salesAgent->id,
            'acreage' => '2.5000',
        ]);
        $this->assertDatabaseHas('sales_offer_entries', [
            'sales_transaction_id' => $transaction->id,
            'amount' => 4_200_000,
            'recorded_by' => $buyer->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('Purchase request progress')
            ->assertSee($transaction->reference)
            ->assertSee('KSh 4,200,000')
            ->assertSee('2.5 acres');
    }

    public function test_buyer_cannot_request_more_acreage_than_the_listing_offers(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 8,
            'offered_acreage' => 6,
        ]);

        $this->actingAs($buyer)
            ->from(route('land.purchase-requests.create', $property))
            ->post(route('land.purchase-requests.store', $property), [
                'requested_price' => 4_200_000,
                'requested_acreage' => 6.5,
            ])
            ->assertSessionHasErrors('requested_acreage');

        $this->assertDatabaseCount('enquiries', 0);
        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_buyer_cannot_create_a_second_active_purchase_request_for_the_same_property(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();
        $payload = ['requested_price' => 1_200_000];

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), $payload)
            ->assertRedirect();
        $existing = Enquiry::query()->sole();

        $this->actingAs($buyer)
            ->post(route('land.purchase-requests.store', $property), $payload)
            ->assertRedirect(route('buyer.enquiries.show', $existing))
            ->assertSessionHas('status', 'You already have an active purchase request for this property.');

        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('sales_transactions', 1);
        $this->assertSame(1, SalesOfferEntry::query()->count());
    }
}
