<?php

namespace Tests\Feature\Admin;

use App\Enums\AgencyFeePayer;
use App\Enums\ClosingEvidenceState;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PaymentReconciliation;
use App\Enums\SaleMode;
use App\Enums\SalesOrigin;
use App\Enums\SalesStage;
use App\Models\AgencyFeeReceipt;
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
use App\Support\Reporting\ReportingFilters;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class AdminSalesPerformanceReportTest extends FoundationTestCase
{
    public function test_completed_sale_appears_in_admin_sales_oversight_kpis(): void
    {
        Notification::fake();
        [$sales, $finance, $seller, $broker, $property, $transaction] = $this->reconciliationFixture();
        $this->completeFinancialSale($sales, $finance, $seller, $broker, $transaction);

        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.oversight.sales', ['range' => '30']))
            ->assertOk()
            ->assertSee('KSh 32,000,000')
            ->assertSee('32.00');
    }

    public function test_pending_sale_is_excluded_from_completed_value(): void
    {
        [$sales, , $seller, $broker, , $transaction] = $this->reconciliationFixture();
        $agencyDesk = app(AgencyFeeDesk::class);
        $agency = $agencyDesk->propose($transaction, $sales, [
            'seller_id' => $seller->id,
            'fee_payer' => AgencyFeePayer::Seller->value,
            'rate_percent' => 3,
        ]);
        $agencyDesk->accept($agency, $sales);

        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.oversight.sales', ['range' => '30']))
            ->assertOk()
            ->assertDontSee('KSh 32,000,000');
    }

    public function test_initiated_agency_receipt_is_excluded_from_collected_totals(): void
    {
        Notification::fake();
        [$sales, $finance, $seller, $broker, , $transaction] = $this->reconciliationFixture();
        $this->completeFinancialSale($sales, $finance, $seller, $broker, $transaction);

        $agency = $transaction->agencyFeeAgreements()->firstOrFail();
        app(AgencyFeeDesk::class)->initiateReceipt($agency, $finance, 'AR-INIT', 100_000, PaymentReconciliation::Unmatched);

        $summary = SalesFinancialReport::between(null, null)->summary();
        $this->assertSame(500_000, $summary['agency_fees_collected']);
        $this->assertSame(460_000, $summary['outstanding_agency_receivables']);
    }

    public function test_financial_report_shows_agreement_exception_without_earned_agreement(): void
    {
        Notification::fake();
        [$sales, $finance, , $broker, , $transaction] = $this->reconciliationFixture();
        $transaction = $this->prepareForClosing($transaction, $sales, 32_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $summary = SalesFinancialReport::between(null, null)->summary();
        $this->assertSame(1, $summary['agency_fee_exceptions']);

        $admin = User::factory()->withRoles('admin')->create();
        $this->actingAs($admin)
            ->get(route('admin.reports.financial', ['range' => '30']))
            ->assertOk()
            ->assertSee('Fee agreement exceptions');
    }

    public function test_property_filter_limits_completed_sales_metrics(): void
    {
        Notification::fake();
        [$sales, $finance, $seller, $broker, $property, $transaction] = $this->reconciliationFixture();
        $this->completeFinancialSale($sales, $finance, $seller, $broker, $transaction);

        $otherProperty = Property::factory()->published()->create();
        SalesTransaction::query()->create([
            'reference' => 'ST-OTHER',
            'property_id' => $otherProperty->id,
            'origin' => SalesOrigin::Enquiry,
            'stage' => SalesStage::Completed,
            'agreed_price' => 1_000_000,
            'completed_at' => now(),
            'opened_at' => now()->subDay(),
        ]);

        $summary = SalesFinancialReport::between(null, null, new ReportingFilters(propertyId: $property->id))->summary();
        $this->assertSame(32_000_000, $summary['completed_sales_value']);
    }

    public function test_csv_export_matches_filtered_completed_sales_count(): void
    {
        Notification::fake();
        [$sales, $finance, $seller, $broker, $property, $transaction] = $this->reconciliationFixture();
        $this->completeFinancialSale($sales, $finance, $seller, $broker, $transaction);

        $admin = User::factory()->withRoles('admin')->create();
        $response = $this->actingAs($admin)
            ->get(route('admin.reports.export', [
                'dataset' => 'completed-sales',
                'range' => '30',
                'property_id' => $property->id,
            ]));

        $response->assertOk();
        $this->assertStringContainsString($transaction->reference, $response->streamedContent());
        $this->assertEquals(1, substr_count($response->streamedContent(), $transaction->reference));
    }

    public function test_buyer_cannot_access_admin_financial_reports(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)
            ->get(route('admin.reports.financial'))
            ->assertForbidden();
    }

    public function test_empty_database_renders_sales_oversight_without_error(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.oversight.sales', ['range' => '30']))
            ->assertOk()
            ->assertSee('Completed sales')
            ->assertDontSee('Unavailable');
    }

    /**
     * @param  array{0: User, 1: User, 2: User, 3: User, 4: Property, 5: SalesTransaction}  $fixture
     */
    private function completeFinancialSale(User $sales, User $finance, User $seller, User $broker, SalesTransaction $transaction): void
    {
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

        $transaction = $this->prepareForClosing($transaction, $sales, 32_000_000);
        $this->actingAs($sales)->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])->assertRedirect();

        $agencyDesk->initiateReceipt($agency->refresh(), $finance, 'AR-TEST-1', 500_000, PaymentReconciliation::Unmatched);
        $receipt = AgencyFeeReceipt::query()->where('reference', 'AR-TEST-1')->firstOrFail();
        $agencyDesk->recordReceipt($receipt, $finance, PaymentReconciliation::Matched);

        $closing = app(ClosingDesk::class);
        $commission = $commission->refresh();
        $closing->approvePayable($commission, $finance);
        $closing->initiatePayment($commission, $finance, 'CP-TEST-1', PaymentReconciliation::Unmatched, 200_000);
        $closing->recordPayment($commission, $finance, PaymentReconciliation::Matched, 'CP-TEST-1');
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
