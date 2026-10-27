<?php

namespace Tests\Feature\Communications;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\OwnerLinkStatus;
use App\Enums\SiteVisitStatus;
use App\Events\EnquirySubmitted;
use App\Jobs\DeliverMessage;
use App\Models\Enquiry;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\PropertyVisitRequest;
use App\Models\SiteVisit;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Services\Buyer\InterestWorkflow;
use App\Services\Buyer\SourcingRequestWriter;
use App\Services\Communications\BuyerInterestNotifier;
use App\Services\Staff\EnquiryDesk;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Foundation\FoundationTestCase;

class BuyerInterestNotificationTest extends FoundationTestCase
{
    public function test_buyer_enquiry_queues_emails_for_buyer_staff_and_seller_without_private_text(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create(['notify_by_email' => false]);
        $property = Property::factory()->published()->create([
            'title' => 'Shela ridge',
            'submitted_by' => $seller->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => 'owner',
            'status' => OwnerLinkStatus::Verified,
        ]);

        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'message' => 'Call me on 0711222333 at buyer@secret.test',
        ]);

        EnquirySubmitted::dispatch($enquiry);
        EnquirySubmitted::dispatch($enquiry);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::BuyerEnquiryAcknowledged)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $this->assertSame(
            1,
            MessageDelivery::query()
                ->where('user_id', $desk->id)
                ->where('event', MessageEvent::BuyerEnquiry)
                ->where('channel', MessageChannel::Email)
                ->count()
        );

        $sellerRow = MessageDelivery::query()
            ->where('user_id', $seller->id)
            ->where('event', MessageEvent::SellerPropertyInterest)
            ->where('channel', MessageChannel::Email)
            ->first();

        $this->assertNotNull($sellerRow);
        $this->assertStringNotContainsString('0711222333', (string) $sellerRow->body);
        $this->assertStringNotContainsString('buyer@secret.test', (string) $sellerRow->body);
    }

    public function test_purchase_request_notifies_sales_staff_and_seller(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create(['notify_by_email' => false]);
        $property = Property::factory()->published()->create([
            'submitted_by' => $seller->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => 'owner',
            'status' => OwnerLinkStatus::Verified,
        ]);

        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'purchase_request' => true,
            'message' => 'Purchase request',
        ]);

        app(BuyerInterestNotifier::class)->purchaseRequestSubmitted($enquiry);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::PurchaseRequestAcknowledged)
                ->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $sales->id)
                ->where('event', MessageEvent::PurchaseRequestStaffAlert)
                ->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $seller->id)
                ->where('event', MessageEvent::SellerPropertyInterest)
                ->exists()
        );
    }

    public function test_broker_listed_property_notifies_broker_and_verified_seller(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['notify_by_email' => false]);
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create(['notify_by_email' => false]);
        $property = Property::factory()->published()->create([
            'title' => 'Broker parcel',
            'submitted_by' => $broker->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => 'owner',
            'status' => OwnerLinkStatus::Verified,
        ]);

        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'message' => 'Interested in this plot.',
        ]);

        EnquirySubmitted::dispatch($enquiry);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $broker->id)
                ->where('event', MessageEvent::BrokerListingInterest)
                ->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $seller->id)
                ->where('event', MessageEvent::SellerPropertyInterest)
                ->exists()
        );
    }

    public function test_sourcing_request_acknowledges_buyer_and_alerts_staff(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        User::factory()->withRoles('staff')->withPermissions('staff.sourcing')->create();

        $request = app(SourcingRequestWriter::class)->create($buyer, [
            'location_label' => 'Shela',
            'centre_latitude' => -2.27,
            'centre_longitude' => 40.9,
            'radius_km' => 5,
        ]);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SourcingRequestAcknowledged)
                ->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('event', MessageEvent::SourcingStaffAlert)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $this->assertSame($request->id, SourcingRequest::query()->firstOrFail()->id);
    }

    public function test_viewing_requested_is_distinct_from_confirmed(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create(['title' => 'Visit plot']);
        $visitRequest = PropertyVisitRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'preferred_on' => now()->addDays(2)->toDateString(),
        ]);

        $visit = app(InterestWorkflow::class)->requestListingVisit($visitRequest);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SiteVisitRequestedAcknowledged)
                ->exists()
        );

        $this->assertFalse(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SiteVisitConfirmed)
                ->exists()
        );

        $confirmedFor = now()->addDays(3)->setTime(10, 0);
        $this->actingAs($desk)->put(route('staff.visits.update', $visit), [
            'status' => SiteVisitStatus::Confirmed->value,
            'confirmed_for' => $confirmedFor->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SiteVisitConfirmed)
                ->exists()
        );
    }

    public function test_visit_reminder_is_not_duplicated(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $property = Property::factory()->published()->create();
        $visit = SiteVisit::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => SiteVisitStatus::Confirmed,
            'requested_for' => now()->addHours(12),
            'confirmed_for' => now()->addHours(12),
        ]);

        $notifier = app(BuyerInterestNotifier::class);
        $notifier->siteVisitReminder($visit);
        $notifier->siteVisitReminder($visit);

        $this->assertSame(
            1,
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SiteVisitReminder)
                ->where('channel', MessageChannel::Email)
                ->count()
        );
    }

    public function test_enquiry_emails_are_not_queued_before_commit(): void
    {
        Bus::fake([DeliverMessage::class]);

        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();

        try {
            DB::transaction(function () use ($buyer, $property): void {
                $enquiry = Enquiry::query()->create([
                    'buyer_id' => $buyer->id,
                    'property_id' => $property->id,
                    'message' => 'Rollback test',
                ]);

                EnquirySubmitted::dispatch($enquiry);
                Bus::assertNotDispatched(DeliverMessage::class);

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
    }

    public function test_enquiry_closed_notifies_buyer(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create();
        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => EnquiryStatus::Responded,
            'lead_status' => LeadStatus::Qualified,
            'message' => 'Please follow up.',
        ]);

        app(EnquiryDesk::class)->updateStatus($enquiry, EnquiryStatus::Closed, LeadStatus::ClosedLost);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::EnquiryClosedBuyer)
                ->exists()
        );
    }

    public function test_document_request_acknowledges_buyer(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create();
        $match = PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => 'shortlisted',
            'approved_at' => now(),
        ]);

        app(InterestWorkflow::class)->requestDocuments($match, $buyer, 'Need title summary.');

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::DocumentAccessAcknowledged)
                ->exists()
        );
    }
}
