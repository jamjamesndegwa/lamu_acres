<?php

namespace Tests\Feature\Sales;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\OfferStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Models\BrokerIntroduction;
use App\Models\County;
use App\Models\Locality;
use App\Models\Offer;
use App\Models\OfferPresentation;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SalesOfferEntry;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Notifications\OfferPresentedNotification;
use App\Notifications\OfferSubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class OfferSalesOriginationTest extends FoundationTestCase
{
    public function test_buyer_creates_offer_with_price_and_acreage(): void
    {
        $property = $this->publishedPartialProperty();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 2_000_000,
            'requested_acreage' => 5,
            'message' => 'Ready to proceed.',
        ])->assertRedirect(route('buyer.offers.index'));

        $offer = Offer::query()->sole();
        $this->assertSame(2_000_000, $offer->amount);
        $this->assertEquals(5.0, (float) $offer->requested_acreage);
    }

    public function test_whole_only_listing_rejects_wrong_acreage_on_offer(): void
    {
        $property = Property::factory()->published()->create([
            'acreage' => 10,
            'offered_acreage' => 10,
            'listing_terms' => ['sale_mode' => SaleMode::Whole->value],
        ]);
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->from(route('land.offers.create', $property))
            ->post(route('land.offers.store', $property), [
                'amount' => 1_000_000,
                'requested_acreage' => 5,
            ])
            ->assertSessionHasErrors('requested_acreage');
    }

    public function test_partial_sale_rejects_acreage_above_available(): void
    {
        $property = $this->publishedPartialProperty();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 1_000_000,
            'requested_acreage' => 50,
        ])->assertSessionHasErrors('requested_acreage');
    }

    public function test_broker_submits_authorized_buyer_offer(): void
    {
        Notification::fake();

        $property = $this->publishedPartialProperty();
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();

        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);
        $introduction->properties()->sync([$property->id]);

        $this->actingAs($broker)->post(route('broker.offers.store'), [
            'broker_introduction_id' => $introduction->id,
            'property_id' => $property->id,
            'amount' => 1_800_000,
            'requested_acreage' => 4,
            'message' => 'Buyer is ready.',
        ])->assertRedirect(route('broker.referrals.show', $introduction));

        $offer = Offer::query()->sole();
        $this->assertSame($buyer->id, $offer->buyer_id);
        $this->assertSame($broker->id, $offer->submitted_by_broker_id);
        $this->assertSame($introduction->id, $offer->broker_introduction_id);
        Notification::assertSentTo($staff, OfferSubmittedNotification::class);
    }

    public function test_unauthorized_broker_cannot_submit_for_another_brokers_introduction(): void
    {
        $property = $this->publishedPartialProperty();
        $ownerBroker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $otherBroker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $ownerBroker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);

        $this->actingAs($otherBroker)->post(route('broker.offers.store'), [
            'broker_introduction_id' => $introduction->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
        ])->assertSessionHasErrors('broker_introduction_id');

        $this->assertDatabaseCount('offers', 0);
    }

    public function test_staff_opens_sales_case_from_buyer_offer_and_preserves_offer_id(): void
    {
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = $this->publishedPartialProperty();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 2_500_000,
            'requested_acreage' => 4,
        ]);

        $offer = Offer::query()->sole();

        $this->actingAs($staff)
            ->post(route('staff.offers.sales-case', $offer))
            ->assertRedirect(route('staff.sales.show', SalesTransaction::query()->sole()));

        $transaction = SalesTransaction::query()->sole();
        $this->assertSame($buyer->id, $transaction->buyer_id);
        $this->assertEquals(4.0, (float) $transaction->acreage);
        $this->assertNull($transaction->agreed_price);
        $this->assertDatabaseHas('sales_offer_entries', [
            'sales_transaction_id' => $transaction->id,
            'offer_id' => $offer->id,
            'amount' => 2_500_000,
        ]);
        $this->assertSame($transaction->id, $offer->refresh()->sales_transaction_id);
    }

    public function test_repeated_sales_case_opening_is_idempotent(): void
    {
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = $this->publishedPartialProperty();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 1_000_000,
            'requested_acreage' => 4,
        ]);
        $offer = Offer::query()->sole();

        $this->actingAs($staff)->post(route('staff.offers.sales-case', $offer));
        $this->actingAs($staff)->post(route('staff.offers.sales-case', $offer));

        $this->assertSame(1, SalesTransaction::query()->count());
        $this->assertSame(1, SalesOfferEntry::query()->where('offer_id', $offer->id)->count());
    }

    public function test_purchase_request_reuses_sales_case_opened_from_offer(): void
    {
        Notification::fake();
        User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = $this->publishedPartialProperty();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 1_000_000,
            'requested_acreage' => 4,
        ]);
        $offer = Offer::query()->sole();
        $this->actingAs($staff)->post(route('staff.offers.sales-case', $offer));

        $this->actingAs($buyer)->post(route('land.purchase-requests.store', $property), [
            'requested_price' => 1_100_000,
            'requested_acreage' => 4,
        ])->assertRedirect();

        $this->assertSame(1, SalesTransaction::query()->count());
        $this->assertGreaterThanOrEqual(2, SalesOfferEntry::query()->count());
    }

    public function test_seller_presentation_notifies_seller_and_syncs_offer_status(): void
    {
        Notification::fake();

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $property = $this->publishedPartialProperty(['submitted_by' => $seller->id]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.properties')->create();

        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 1_500_000,
            'requested_acreage' => 4,
            'status' => OfferStatus::UnderReview,
        ]);

        $this->actingAs($staff)->post(route('staff.offers.present', $offer), [
            'summary' => 'Buyer offer ready for your review.',
        ])->assertRedirect();

        Notification::assertSentTo($seller, OfferPresentedNotification::class);

        $presentation = OfferPresentation::query()->sole();
        $this->actingAs($seller)->post(route('seller.offers.respond', $presentation), [
            'seller_response' => 'accept',
        ])->assertRedirect();

        $this->assertSame(OfferStatus::Accepted, $offer->refresh()->status);
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);
    }

    public function test_stale_presentation_cannot_overwrite_offer_state(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $property = Property::factory()->published()->create(['submitted_by' => $seller->id]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();

        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
            'status' => OfferStatus::UnderReview,
        ]);

        $older = OfferPresentation::query()->create([
            'offer_id' => $offer->id,
            'property_id' => $property->id,
            'presented_by' => $staff->id,
            'amount' => 1_000_000,
            'summary' => 'Older presentation.',
        ]);
        OfferPresentation::query()->create([
            'offer_id' => $offer->id,
            'property_id' => $property->id,
            'presented_by' => $staff->id,
            'amount' => 1_100_000,
            'summary' => 'Newer presentation.',
        ]);

        $this->actingAs($seller)->post(route('seller.offers.respond', $older), [
            'seller_response' => 'decline',
        ])->assertSessionHasErrors('seller_response');

        $this->assertSame(OfferStatus::UnderReview, $offer->refresh()->status);
    }

    public function test_seller_acceptance_does_not_complete_sale_or_set_agreed_price(): void
    {
        Notification::fake();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $property = $this->publishedPartialProperty(['submitted_by' => $seller->id]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 2_000_000,
            'requested_acreage' => 4,
        ]);
        $offer = Offer::query()->sole();
        $this->actingAs($sales)->post(route('staff.offers.sales-case', $offer));
        $transaction = SalesTransaction::query()->sole();

        $presentation = OfferPresentation::query()->create([
            'offer_id' => $offer->id,
            'property_id' => $property->id,
            'presented_by' => $sales->id,
            'amount' => $offer->amount,
            'summary' => 'Presented.',
        ]);
        $this->actingAs($seller)->post(route('seller.offers.respond', $presentation), ['seller_response' => 'accept']);

        $this->assertNull($transaction->refresh()->agreed_price);
        $this->assertNotSame('completed', $transaction->stage->value);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedPartialProperty(array $overrides = []): Property
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu-offer', 'is_active' => true]);
        $locality = Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Manda',
            'slug' => 'manda-offer',
            'kind' => 'place',
            'is_active' => true,
        ]);

        return Property::factory()->published()->listedIn($locality)->create(array_merge([
            'acreage' => 20,
            'offered_acreage' => 12,
            'minimum_acreage' => 4,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'price_amount' => 6_000_000,
        ], $overrides));
    }
}
