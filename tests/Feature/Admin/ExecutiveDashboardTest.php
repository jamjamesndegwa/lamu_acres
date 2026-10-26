<?php

namespace Tests\Feature\Admin;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CommissionFunding;
use App\Enums\CommissionStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\QualifyingEvent;
use App\Enums\SalesOrigin;
use App\Enums\SalesStage;
use App\Enums\SourcingRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Models\BrokerIntroduction;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyMatch;
use App\Models\SalesAudit;
use App\Models\SalesTransaction;
use App\Models\SourcingRequest;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Models\VerificationItem;
use Tests\Feature\Foundation\FoundationTestCase;

class ExecutiveDashboardTest extends FoundationTestCase
{
    public function test_an_empty_dashboard_reports_zero_and_no_activity(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Published and available')
            ->assertSee('Listings by status')
            ->assertSee('Sales pipeline')
            ->assertSee('Commission and premium revenue')
            ->assertSee('Activity over the period')
            ->assertSee('Completed sales')
            ->assertSee('Open sourcing requests')
            ->assertSee('Approved brokers')
            ->assertSee('Commission earned')
            ->assertSee('Premium revenue received')
            ->assertSee('Active subscribers')
            ->assertSee('Awaiting verification')
            ->assertSee('No activity yet')
            ->assertDontSee('Unavailable')
            ->assertDontSee('Not available yet');

        foreach (['sales', 'inventory', 'buyers', 'brokers', 'subscriptions'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.oversight.'.$tab))
                ->assertOk()
                ->assertSee('No activity yet')
                ->assertDontSee('Unavailable');
        }
    }

    public function test_business_figures_follow_records_permissions_and_the_selected_period(): void
    {
        $admin = User::factory()->withRoles('admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $broker = User::factory()->withRoles('broker')->create();
        User::factory()->withRoles('broker')->create(['suspended_at' => now()]);

        Property::factory()->published()->create(['title' => 'Shela acre']);
        Property::factory()->published()->create();
        Property::factory()->unavailable()->create();
        Property::factory()->create(['status' => PropertyStatus::Reserved, 'title' => 'Reserved plot']);
        Property::factory()->create(['status' => PropertyStatus::Sold]);
        Property::factory()->create(['status' => PropertyStatus::Review]);
        Property::factory()->create(['status' => PropertyStatus::Draft, 'title' => 'Hidden draft plot']);

        $recentProperty = Property::factory()->published()->create(['title' => 'Recent sale plot']);
        $oldProperty = Property::factory()->published()->create(['title' => 'Old sale plot']);
        $openProperty = Property::factory()->published()->create(['title' => 'Open sale plot']);

        $introduction = BrokerIntroduction::query()->create([
            'reference' => 'BR-ACCEPTED',
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $openProperty->id,
            'status' => BrokerIntroductionStatus::Accepted,
            'introduced_at' => now()->subDay(),
        ]);
        BrokerIntroduction::query()->create([
            'reference' => 'BR-ACTIVE',
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $recentProperty->id,
            'status' => BrokerIntroductionStatus::Active,
            'introduced_at' => now()->subDay(),
        ]);

        $openSale = SalesTransaction::query()->create([
            'reference' => 'ST-OPEN',
            'property_id' => $openProperty->id,
            'buyer_id' => $buyer->id,
            'origin' => SalesOrigin::Introduction,
            'broker_introduction_id' => $introduction->id,
            'stage' => SalesStage::Enquiry,
            'opened_at' => now()->subDay(),
        ]);
        SalesTransaction::query()->create([
            'reference' => 'ST-RECENT',
            'property_id' => $recentProperty->id,
            'buyer_id' => $buyer->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Completed,
            'agreed_price' => 1_500_000,
            'completed_at' => now()->subDays(2),
            'opened_at' => now()->subDays(10),
        ]);
        $oldSale = SalesTransaction::query()->create([
            'reference' => 'ST-OLD',
            'property_id' => $oldProperty->id,
            'buyer_id' => $buyer->id,
            'origin' => SalesOrigin::Enquiry,
            'broker_introduction_id' => $introduction->id,
            'stage' => SalesStage::Completed,
            'agreed_price' => 9_000_000,
            'completed_at' => now()->subDays(40),
            'opened_at' => now()->subDays(50),
        ]);

        SalesAudit::query()->create([
            'sales_transaction_id' => $openSale->id,
            'actor_id' => $admin->id,
            'event' => 'offer recorded',
        ]);

        PropertyAudit::query()->create([
            'property_id' => $recentProperty->id,
            'actor_id' => $admin->id,
            'action' => 'published',
        ]);

        Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $recentProperty->id,
            'message' => 'Is the recent plot still available?',
        ]);
        $oldEnquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $oldProperty->id,
            'message' => 'This enquiry is outside the period.',
        ]);
        $oldEnquiry->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

        SourcingRequest::query()->create([
            'reference' => 'SR-OPEN',
            'buyer_id' => $buyer->id,
            'status' => SourcingRequestStatus::Submitted,
            'location_label' => 'Shela',
            'submitted_at' => now(),
        ]);
        $matched = SourcingRequest::query()->create([
            'reference' => 'SR-MATCHED',
            'buyer_id' => $buyer->id,
            'status' => SourcingRequestStatus::InProgress,
            'location_label' => 'Shela',
            'submitted_at' => now(),
        ]);
        PropertyMatch::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $recentProperty->id,
            'sourcing_request_id' => $matched->id,
        ]);
        SourcingRequest::query()->create([
            'reference' => 'SR-CLOSED',
            'buyer_id' => $buyer->id,
            'status' => SourcingRequestStatus::Closed,
            'location_label' => 'Closed bay',
            'closed_at' => now(),
        ]);
        $oldRequest = SourcingRequest::query()->create([
            'reference' => 'SR-OLD',
            'buyer_id' => $buyer->id,
            'status' => SourcingRequestStatus::Submitted,
            'location_label' => 'Faza',
            'submitted_at' => now()->subDays(40),
        ]);
        $oldRequest->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

        $earnedSale = SalesTransaction::query()->create([
            'reference' => 'ST-EARNED',
            'property_id' => $recentProperty->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Contracting,
            'opened_at' => now(),
        ]);
        $payableSale = SalesTransaction::query()->create([
            'reference' => 'ST-PAYABLE',
            'property_id' => $recentProperty->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Contracting,
            'opened_at' => now(),
        ]);
        CommissionAgreement::query()->create([
            'reference' => 'CA-EARNED',
            'sales_transaction_id' => $earnedSale->id,
            'broker_id' => $broker->id,
            'property_id' => $recentProperty->id,
            'earned_amount' => 100_000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => CommissionStatus::Earned,
        ]);
        $payable = CommissionAgreement::query()->create([
            'reference' => 'CA-PAYABLE',
            'sales_transaction_id' => $payableSale->id,
            'broker_id' => $broker->id,
            'property_id' => $recentProperty->id,
            'earned_amount' => 50_000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => CommissionStatus::Payable,
        ]);
        $paidAgreement = CommissionAgreement::query()->create([
            'reference' => 'CA-PAID',
            'sales_transaction_id' => $oldSale->id,
            'broker_id' => $broker->id,
            'property_id' => $oldProperty->id,
            'earned_amount' => 80_000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => CommissionStatus::Paid,
        ]);
        CommissionPayment::query()->create([
            'commission_agreement_id' => $payable->id,
            'amount' => 25_000,
            'reference' => 'CP-RECENT',
            'status' => 'paid',
            'reconciliation' => PaymentReconciliation::Matched,
            'initiated_by' => $admin->id,
            'paid_at' => now()->subDay(),
        ]);
        CommissionPayment::query()->create([
            'commission_agreement_id' => $paidAgreement->id,
            'amount' => 80_000,
            'reference' => 'CP-OLD',
            'status' => 'paid',
            'reconciliation' => PaymentReconciliation::Matched,
            'initiated_by' => $admin->id,
            'paid_at' => now()->subDays(40),
        ]);

        $member = User::factory()->withRoles('buyer')->create(['name' => 'Active Member']);
        $expiringMember = User::factory()->withRoles('buyer')->create(['name' => 'Expiring Member']);
        $plan = SubscriptionPlan::investorPass();
        $active = Subscription::query()->create([
            'user_id' => $member->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(2),
        ]);
        Subscription::query()->create([
            'user_id' => $expiringMember->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(10),
        ]);
        Subscription::query()->create([
            'user_id' => $buyer->id,
            'subscription_plan_id' => $plan->id,
            'status' => SubscriptionStatus::Cancelled,
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subDay(),
            'cancelled_at' => now()->subDay(),
        ]);
        SubscriptionEvent::query()->create([
            'subscription_id' => $active->id,
            'actor_id' => $admin->id,
            'action' => 'renewed',
        ]);
        SubscriptionEvent::query()->create([
            'subscription_id' => $active->id,
            'actor_id' => $admin->id,
            'action' => 'activated',
        ]);

        $invoice = SubscriptionInvoice::query()->create([
            'subscription_id' => $active->id,
            'number' => 'INV-100',
            'amount' => 5000,
            'currency' => 'KES',
            'status' => 'paid',
            'policy' => 'Receipt follows a reconciled provider payment.',
        ]);
        $paid = SubscriptionPayment::query()->create([
            'subscription_id' => $active->id,
            'invoice_id' => $invoice->id,
            'user_id' => $member->id,
            'idempotency_key' => 'pay-settled',
            'phone' => '254700000001',
            'amount' => 5000,
            'currency' => 'KES',
            'status' => PaymentStatus::Succeeded,
        ]);
        SubscriptionReceipt::query()->create([
            'payment_id' => $paid->id,
            'invoice_id' => $invoice->id,
            'provider_receipt' => 'RCPT100',
            'amount' => 5000,
            'currency' => 'KES',
            'issued_at' => now()->subDay(),
        ]);
        $pendingInvoice = SubscriptionInvoice::query()->create([
            'subscription_id' => $active->id,
            'number' => 'INV-PENDING',
            'amount' => 9000,
            'currency' => 'KES',
            'status' => 'open',
            'policy' => 'A pending attempt is not revenue.',
        ]);
        SubscriptionPayment::query()->create([
            'subscription_id' => $active->id,
            'invoice_id' => $pendingInvoice->id,
            'user_id' => $member->id,
            'idempotency_key' => 'pay-pending',
            'phone' => '254700000001',
            'amount' => 9000,
            'currency' => 'KES',
            'status' => PaymentStatus::Pending,
        ]);

        $awaiting = Property::factory()->create(['title' => 'Awaiting checks']);
        VerificationItem::query()->create([
            'property_id' => $awaiting->id,
            'category' => VerificationCategory::TitleOwnership,
            'state' => VerificationItemState::UnderReview,
        ]);
        $failed = Property::factory()->create(['title' => 'Failed checks']);
        VerificationItem::query()->create([
            'property_id' => $failed->id,
            'category' => VerificationCategory::TitleOwnership,
            'state' => VerificationItemState::Failed,
        ]);
        VerificationItem::query()->create([
            'property_id' => $failed->id,
            'category' => VerificationCategory::Encumbrances,
            'state' => VerificationItemState::Expired,
        ]);
        VerificationItem::query()->create([
            'property_id' => $failed->id,
            'category' => VerificationCategory::Zoning,
            'state' => VerificationItemState::Failed,
        ]);

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard', ['range' => '30']));

        $dashboard->assertOk()
            ->assertSeeInOrder(['Published and available', '5'])
            ->assertSeeInOrder(['Reserved', '1'])
            ->assertSeeInOrder(['Sold', '1'])
            ->assertSeeInOrder(['Pending review', '1'])
            ->assertSeeInOrder(['Completed value', 'KSh 1,500,000'])
            ->assertDontSee('KSh 9,000,000')
            ->assertSeeInOrder(['Open sourcing requests', '3'])
            ->assertSeeInOrder(['Unmatched requirements', '2'])
            ->assertSee('Shela')
            ->assertDontSee('Faza')
            ->assertSeeInOrder(['Approved brokers', '1'])
            ->assertSeeInOrder(['Accepted introductions', '1'])
            ->assertSeeInOrder(['Deals in progress', '1'])
            ->assertSeeInOrder(['Completed referred deals', '0'])
            ->assertSeeInOrder(['Commission earned', 'KSh 100,000'])
            ->assertSeeInOrder(['Commission payable', 'KSh 50,000'])
            ->assertSeeInOrder(['Commission paid', 'KSh 25,000'])
            ->assertDontSee('KSh 80,000')
            ->assertSeeInOrder(['Premium revenue received', 'KSh 5,000'])
            ->assertDontSee('KSh 9,000')
            ->assertSeeInOrder(['Active subscribers', '2'])
            ->assertSeeInOrder(['Pending payment attempts', '1'])
            ->assertSeeInOrder(['Expiring within 30 days', '1'])
            ->assertSeeInOrder(['Subscription renewals', '1'])
            ->assertSeeInOrder(['Awaiting verification', '1'])
            ->assertSeeInOrder(['Failed or expired critical checks', '2'])
            ->assertSeeInOrder(['Verification workload', '1'])
            ->assertSee('Sale ST-OPEN: offer recorded')
            ->assertSee('Property Recent sale plot: published')
            ->assertSee('Premium receipt RCPT100 for KSh 5,000')
            ->assertDontSee('No activity yet');

        $this->actingAs($admin)
            ->get(route('admin.dashboard', [
                'range' => 'custom',
                'from' => now()->subDays(45)->toDateString(),
                'to' => now()->subDays(35)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('KSh 9,000,000')
            ->assertDontSee('KSh 1,500,000')
            ->assertSee('KSh 80,000')
            ->assertDontSee('KSh 25,000')
            ->assertSee('Faza')
            ->assertDontSee('Shela')
            ->assertSeeInOrder(['Completed referred deals', '1']);

        $this->actingAs($admin)
            ->get(route('admin.oversight.sales'))
            ->assertOk()
            ->assertSee('ST-OPEN')
            ->assertSee('ST-RECENT')
            ->assertSee('Sales performance');

        $this->actingAs($admin)
            ->get(route('admin.oversight.inventory'))
            ->assertOk()
            ->assertSee('Shela acre')
            ->assertSee('Reserved plot')
            ->assertDontSee('Hidden draft plot');

        $this->actingAs($admin)
            ->get(route('admin.oversight.subscriptions'))
            ->assertOk()
            ->assertSee('Active Member')
            ->assertSee('Pending payment attempts')
            ->assertSee('KSh 9,000')
            ->assertSee('Premium receipts in this period')
            ->assertSee('RCPT100')
            ->assertSee('KSh 5,000');
    }

    public function test_oversight_is_refused_without_permission_and_outside_the_admin_workspace(): void
    {
        $limited = User::factory()->withPermissions('workspace.admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($limited)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('You are not authorized to view business oversight.')
            ->assertDontSee('Published and available')
            ->assertDontSee('KSh 0');

        $this->actingAs($limited)
            ->get(route('admin.oversight.sales'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_custom_range_requires_both_dates(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['range' => 'custom']))
            ->assertSessionHasErrors(['from', 'to']);
    }
}
