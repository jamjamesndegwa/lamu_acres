<?php

namespace Tests\Feature\Sales;

use App\Enums\AgencyFeePayer;
use App\Enums\ClosingEvidenceState;
use App\Enums\CommissionStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PaymentReconciliation;
use App\Enums\SaleMode;
use App\Enums\SalesStage;
use App\Models\AgencyFeeReceipt;
use App\Models\CommissionPayment;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\AgencyFeeDesk;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\CommissionDesk;
use App\Services\Sales\SalesFinancialReport;
use App\Services\Sales\SalesPipeline;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesFinancialIntegrityTest extends FoundationTestCase
{
    public function test_reconciliation_example_with_partial_collection_and_broker_payment(): void
    {
        Notification::fake();

        [$sales, $finance, $seller, $broker, $property, $transaction] = $this->reconciliationFixture();

        $agencyDesk = app(AgencyFeeDesk::class);
        $agency = $agencyDesk->propose($transaction, $sales, [
            'seller_id' => $seller->id,
            'fee_payer' => AgencyFeePayer::Seller->value,
            'rate_percent' => 3,
            'terms' => 'Test fixture only.',
        ]);
        $agencyDesk->accept($agency, $sales);

        $commissionDesk = app(CommissionDesk::class);
        $commission = $commissionDesk->propose($transaction, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 1,
            'funding_party' => 'brokerage',
            'protection_ends_at' => now()->addYear(),
        ]);
        $commissionDesk->accept($commission, $sales);

        $transaction = $this->prepareForClosing($transaction, $sales, 32_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $agency->refresh();
        $commission->refresh();

        $this->assertSame(960_000, $agency->earned_amount);
        $this->assertSame(320_000, $commission->earned_amount);

        $agencyDesk->initiateReceipt($agency, $finance, 'AR-TEST-1', 500_000, PaymentReconciliation::Unmatched);
        $receipt = AgencyFeeReceipt::query()->where('reference', 'AR-TEST-1')->firstOrFail();
        $this->assertSame('initiated', $receipt->status);
        $agencyDesk->recordReceipt($receipt, $finance, PaymentReconciliation::Matched);
        $agency->refresh();
        $this->assertSame(500_000, $agency->collected_amount);
        $this->assertSame(460_000, $agency->outstandingReceivable());

        $closing = app(ClosingDesk::class);
        $closing->approvePayable($commission, $finance);
        $closing->initiatePayment($commission, $finance, 'CP-TEST-1', PaymentReconciliation::Unmatched, 200_000);
        $this->assertSame('initiated', CommissionPayment::query()->where('reference', 'CP-TEST-1')->value('status'));
        $closing->recordPayment($commission, $finance, PaymentReconciliation::Matched, 'CP-TEST-1');
        $commission->refresh();
        $this->assertSame(200_000, $commission->paid_amount);
        $this->assertSame(CommissionStatus::Payable, $commission->status);

        $summary = SalesFinancialReport::between(null, null)->summary();
        $this->assertSame(32_000_000, $summary['completed_sales_value']);
        $this->assertSame(960_000, $summary['gross_agency_fees_earned']);
        $this->assertSame(500_000, $summary['agency_fees_collected']);
        $this->assertSame(320_000, $summary['broker_commissions_earned']);
        $this->assertSame(200_000, $summary['broker_commissions_paid']);

        $this->actingAs($finance)
            ->post(route('staff.agency-collections.initiate', $agency), [
                'reference' => 'AR-TEST-1',
                'amount' => 100,
                'reconciliation' => PaymentReconciliation::Unmatched->value,
            ])
            ->assertSessionHasErrors('reference');

        $this->actingAs($finance)
            ->post(route('staff.settlements.initiate', $commission), [
                'reference' => 'CP-TEST-1',
                'amount' => 100,
                'reconciliation' => PaymentReconciliation::Unmatched->value,
            ])
            ->assertSessionHasErrors('reference');
    }

    public function test_uncompleted_sale_does_not_earn_agency_or_commission(): void
    {
        [$sales, , $seller, $broker, , $transaction] = $this->reconciliationFixture();
        $agencyDesk = app(AgencyFeeDesk::class);
        $agency = $agencyDesk->propose($transaction, $sales, [
            'seller_id' => $seller->id,
            'fee_payer' => AgencyFeePayer::Seller->value,
            'rate_percent' => 3,
        ]);
        $agencyDesk->accept($agency, $sales);

        $commissionDesk = app(CommissionDesk::class);
        $commission = $commissionDesk->propose($transaction, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 1,
            'funding_party' => 'brokerage',
            'protection_ends_at' => now()->addYear(),
        ]);
        $commissionDesk->accept($commission, $sales);

        $this->assertNull($agencyDesk->eligibleAmount($agency, $transaction));
        $this->assertNull(app(CommissionDesk::class)->eligibleAmount($commission, $transaction));
    }

    public function test_commission_requires_broker_mandate(): void
    {
        [$sales, , , , , $transaction] = $this->reconciliationFixture();
        $outsider = User::factory()->withRoles('broker')->create();

        $this->expectException(ValidationException::class);
        app(CommissionDesk::class)->propose($transaction, $sales, [
            'broker_id' => $outsider->id,
            'rate_percent' => 1,
            'funding_party' => 'seller',
            'protection_ends_at' => now()->addYear(),
        ]);
    }

    public function test_historical_agreement_terms_survive_listing_price_change(): void
    {
        Notification::fake();
        [$sales, , , $broker, $property, $transaction] = $this->reconciliationFixture();
        $commissionDesk = app(CommissionDesk::class);
        $commission = $commissionDesk->propose($transaction, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 10,
            'funding_party' => 'seller',
            'protection_ends_at' => now()->addYear(),
        ]);
        $commissionDesk->accept($commission, $sales);

        $property->update(['price_amount' => 99_999_999]);
        $transaction = $this->prepareForClosing($transaction, $sales, 8_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $this->assertSame(800_000, $commission->refresh()->earned_amount);
    }

    public function test_seller_sees_agency_fee_but_not_broker_commission_on_completed_sale(): void
    {
        Notification::fake();
        [$sales, , $seller, $broker, , $transaction] = $this->reconciliationFixture();
        app(AgencyFeeDesk::class)->accept(
            app(AgencyFeeDesk::class)->propose($transaction, $sales, [
                'seller_id' => $seller->id,
                'fee_payer' => AgencyFeePayer::Seller->value,
                'rate_percent' => 3,
            ]),
            $sales,
        );
        app(CommissionDesk::class)->accept(
            app(CommissionDesk::class)->propose($transaction, $sales, [
                'broker_id' => $broker->id,
                'rate_percent' => 1,
                'funding_party' => 'brokerage',
                'protection_ends_at' => now()->addYear(),
            ]),
            $sales,
        );
        $transaction = $this->prepareForClosing($transaction, $sales, 32_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $this->actingAs($seller)
            ->get(route('seller.sales.show', $transaction))
            ->assertOk()
            ->assertSee('960,000')
            ->assertDontSee('320,000');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User, 4: Property, 5: SalesTransaction}
     */
    private function reconciliationFixture(): array
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose', 'agency.propose')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance', 'commissions.approve', 'commissions.record', 'agency.record')->create();

        $property = Property::factory()->published()->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'submitted_by' => $broker->id,
        ]);
        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $transaction = app(SalesPipeline::class)->open($sales, [
            'origin' => 'enquiry',
            'property_id' => $property->id,
            'enquiry_id' => Enquiry::query()->create([
                'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
                'property_id' => $property->id,
                'message' => 'Buy.',
            ])->id,
            'acreage' => 32,
        ]);

        return [$sales, $finance, $seller, $broker, $property, $transaction];
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
        $closing->review($transaction, $sales, 'authorized', 'Authorized.');
        foreach (array_keys(ClosingDesk::EVIDENCE) as $code) {
            $closing->evidence($transaction, $sales, $code, ClosingEvidenceState::Accepted, 'OK');
        }

        return $transaction->refresh();
    }
}
