<?php

namespace Tests\Feature\Communications;

use App\Enums\CommissionFunding;
use App\Enums\CommissionStatus;
use App\Enums\DocumentAccessStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\OfferStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\QualifyingEvent;
use App\Enums\SalesOrigin;
use App\Enums\SalesOutcome;
use App\Enums\SalesStage;
use App\Jobs\DeliverMessage;
use App\Models\CommissionAgreement;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\SalesTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Buyer\InterestWorkflow;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\SalesPipeline;
use App\Services\Subscription\Memberships;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesFinanceNotificationTest extends FoundationTestCase
{
    public function test_buyer_offer_submission_queues_acknowledgment_and_staff_email(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();
        $match = PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => 'shortlisted',
            'approved_at' => now(),
        ]);

        app(InterestWorkflow::class)->submitOffer($match, $buyer, 1_500_000, null, 'Ready to proceed after verification.');

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::OfferSubmittedAcknowledged)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $staff->id)
                ->where('event', MessageEvent::OfferSubmittedStaffAlert)
                ->exists()
        );
    }

    public function test_seller_acceptance_does_not_emit_sale_completed_email(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $property = Property::factory()->published()->create(['submitted_by' => $seller->id]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => 'owner',
            'status' => OwnerLinkStatus::Verified,
        ]);
        $match = PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => 'shortlisted',
            'approved_at' => now(),
        ]);
        $offer = app(InterestWorkflow::class)->submitOffer($match, $buyer, 2_000_000, null, null);
        $offer->update(['status' => OfferStatus::UnderReview]);

        app(InterestWorkflow::class)->reflectSellerPresentation($offer, OfferStatus::Accepted, $seller);

        $this->assertFalse(
            MessageDelivery::query()->where('event', MessageEvent::ClosingCompleted)->exists()
        );

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::OfferStatus)
                ->exists()
        );
    }

    public function test_agreed_price_queues_sale_terms_email(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();
        $transaction = SalesTransaction::query()->create([
            'reference' => 'ST-TEST-1',
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'assigned_to' => $staff->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Negotiation,
            'acreage' => 4,
            'lock_version' => 0,
            'opened_at' => now(),
        ]);

        app(SalesPipeline::class)->agreePrice($transaction, 3_500_000, $staff, 0);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SaleTermsAgreed)
                ->exists()
        );
    }

    public function test_commission_payment_initiation_does_not_send_paid_notice(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['notify_by_email' => false]);
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance', 'commissions.record')->create();
        $property = Property::factory()->published()->create();
        $transaction = SalesTransaction::query()->create([
            'reference' => 'ST-COM-1',
            'property_id' => $property->id,
            'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
            'assigned_to' => $finance->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Completed,
            'acreage' => 2,
            'lock_version' => 1,
            'opened_at' => now(),
            'completed_at' => now(),
        ]);
        $agreement = CommissionAgreement::query()->create([
            'reference' => 'CA-TEST-1',
            'sales_transaction_id' => $transaction->id,
            'property_id' => $property->id,
            'broker_id' => $broker->id,
            'status' => CommissionStatus::Payable,
            'earned_amount' => 50_000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
        ]);

        app(ClosingDesk::class)->initiatePayment(
            $agreement,
            $finance,
            app(ClosingDesk::class)->paymentReference(),
            PaymentReconciliation::Matched,
        );

        $this->assertFalse(
            MessageDelivery::query()
                ->where('event', MessageEvent::CommissionPaid)
                ->exists()
        );
    }

    public function test_document_access_decision_queues_buyer_email(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create();
        $match = PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => 'shortlisted',
            'approved_at' => now(),
        ]);
        $request = app(InterestWorkflow::class)->requestDocuments($match, $buyer, 'Title summary please.');

        app(InterestWorkflow::class)->decideDocuments($request, DocumentAccessStatus::Approved, $staff);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::DocumentAccessDecided)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_subscription_activation_queues_email(): void
    {
        $member = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $plan = SubscriptionPlan::investorPass();
        $subscription = Subscription::query()->create([
            'user_id' => $member->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
        ]);

        app(Memberships::class)->activate($staff, $subscription);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $member->id)
                ->where('event', MessageEvent::SubscriptionActivated)
                ->exists()
        );
    }

    public function test_sale_outcome_terminal_queues_buyer_email(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();
        $transaction = SalesTransaction::query()->create([
            'reference' => 'ST-OUT-1',
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'assigned_to' => $staff->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Negotiation,
            'acreage' => 3,
            'lock_version' => 0,
            'opened_at' => now(),
        ]);

        app(SalesPipeline::class)->outcome($transaction, SalesOutcome::Cancelled, $staff, 0, 'Buyer withdrew interest.');

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::SaleOutcomeUpdated)
                ->exists()
        );
    }

    public function test_sales_finance_emails_dispatch_after_commit(): void
    {
        Bus::fake([DeliverMessage::class]);

        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();
        $match = PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => 'shortlisted',
            'approved_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($buyer, $match): void {
                app(InterestWorkflow::class)->submitOffer($match, $buyer, 900_000, null, null);
                Bus::assertNotDispatched(DeliverMessage::class);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
    }
}
