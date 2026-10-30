<?php

namespace Tests\Feature\Sales;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\ClosingEvidenceState;
use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionStatus;
use App\Enums\DealKind;
use App\Enums\FeeCalculationBasis;
use App\Enums\PaymentReconciliation;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\QualifyingEvent;
use App\Enums\RentFrequency;
use App\Enums\SalesOutcome;
use App\Enums\SalesStage;
use App\Models\AgencyFeePolicy;
use App\Models\BrokerIntroduction;
use App\Models\CommissionAgreement;
use App\Models\CommissionPolicy;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyTransaction;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\AgencyFeeDesk;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\CommissionDesk;
use App\Services\Sales\LeaseCommercialTerms;
use App\Services\Sales\SalesPipeline;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class LeaseCommercialIntegrationTest extends FoundationTestCase
{
    public function test_lease_confirmation_earns_commission_and_agency_fee_once(): void
    {
        [$staff, $finance, $broker, $lessor, $lessee, $property, $deal] = $this->leaseDeal();

        $commissions = app(CommissionDesk::class);
        $agencyFees = app(AgencyFeeDesk::class);
        $closing = app(ClosingDesk::class);
        $pipeline = app(SalesPipeline::class);

        $commission = $commissions->propose($deal, $staff, $this->leaseCommissionProposal($broker->id));
        $this->assertSame(CommissionCalculationBasis::FirstYearRentPercent, $commission->calculation_basis);
        $this->assertSame(QualifyingEvent::LeaseConfirmed, $commission->qualifying_event);
        $commissions->accept($commission, $staff);

        $agency = $agencyFees->propose($deal, $staff, $this->leaseAgencyProposal($lessor->id));
        $this->assertSame(FeeCalculationBasis::FirstYearRentPercent, $agency->calculation_basis);
        $agencyFees->accept($agency, $staff);

        $deal = $this->prepareLeaseForConfirmation($deal, $staff);
        $closing->confirmLease($deal, $staff, $deal->lock_version);

        $commission->refresh();
        $agency->refresh();
        $deal->refresh();

        $firstYearRent = app(LeaseCommercialTerms::class)->firstYearContractedRent($deal);
        $this->assertSame(1_440_000, $firstYearRent);
        $this->assertSame(216_000, $commission->earned_amount);
        $this->assertSame(144_000, $agency->earned_amount);
        $this->assertSame(CommissionStatus::Earned, $commission->status);
        $this->assertSame(CommissionStatus::Earned, $agency->status);
        $this->assertNotNull($deal->property_transaction_id);
        $this->assertSame(PropertyStatus::Leased, $property->refresh()->status);

        $this->expectException(ValidationException::class);
        $closing->confirmLease($deal, $staff, $deal->lock_version);
    }

    public function test_fixed_and_rent_based_lease_commission_calculations(): void
    {
        [, , $broker, , , , $deal] = $this->leaseDeal();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose')->create();
        $desk = app(CommissionDesk::class);
        $terms = app(LeaseCommercialTerms::class);

        $deal = app(SalesPipeline::class)->updateLeaseCommercialTerms($deal, $staff, $deal->lock_version, [
            'lease_starts_on' => now()->toDateString(),
            'lease_ends_on' => now()->addMonths(6)->toDateString(),
            'rent_frequency' => RentFrequency::Monthly->value,
            'agreed_rent' => 100_000,
            'acreage' => 2,
        ]);

        $partialYear = $terms->firstYearContractedRent($deal);
        $this->assertSame(600_000, $partialYear);

        $fixed = $desk->propose($deal, $staff, [
            'broker_id' => $broker->id,
            'calculation_basis' => CommissionCalculationBasis::Fixed->value,
            'fixed_amount' => 75_000,
            'funding_party' => 'lessor',
            'protection_ends_at' => now()->addMonths(3),
        ]);
        $desk->accept($fixed, $staff);
        $earning = $desk->qualifyForEarning($fixed, $this->completedLeaseDeal($deal->fresh()));
        $this->assertSame(75_000, $earning['amount']);

        [, , $broker, , , , $secondDeal] = $this->leaseDeal();
        $secondDeal = app(SalesPipeline::class)->updateLeaseCommercialTerms($secondDeal, $staff, $secondDeal->lock_version, [
            'lease_starts_on' => now()->toDateString(),
            'rent_frequency' => RentFrequency::Monthly->value,
            'agreed_rent' => 120_000,
            'acreage' => 2,
        ]);
        $multiple = $desk->propose($secondDeal, $staff, [
            'broker_id' => $broker->id,
            'calculation_basis' => CommissionCalculationBasis::MonthlyRentMultiple->value,
            'monthly_rent_multiplier' => 2,
            'funding_party' => 'lessor',
            'protection_ends_at' => now()->addMonths(3),
        ]);
        $desk->accept($multiple, $staff);
        $this->assertSame(240_000, $desk->qualifyForEarning($multiple, $this->completedLeaseDeal($secondDeal))['amount']);
    }

    public function test_broker_mandate_and_accepted_agreement_are_required_before_earning(): void
    {
        [$staff, , , , , , $deal] = $this->leaseDeal();
        $closing = app(ClosingDesk::class);
        $unauthorizedBroker = User::factory()->withRoles('broker')->create();
        $desk = app(CommissionDesk::class);
        $deal->load('introduction');
        $authorizedBroker = $deal->introduction->broker;

        $this->expectException(ValidationException::class);
        $desk->propose($deal, $staff, [
            'broker_id' => $unauthorizedBroker->id,
            'funding_party' => 'lessor',
            'protection_ends_at' => now()->addMonths(3),
        ]);

        $commission = $desk->propose($deal->refresh(), $staff, $this->leaseCommissionProposal($authorizedBroker->id));

        $prepared = $this->prepareLeaseForConfirmation($deal->refresh(), $staff);
        $this->expectException(ValidationException::class);
        $closing->confirmLease($prepared, $staff, $prepared->lock_version);

        $desk->accept($commission, $staff);
        $prepared = $this->prepareLeaseForConfirmation($deal->refresh(), $staff);
        $closing->confirmLease($prepared, $staff, $prepared->lock_version);
        $this->assertSame(CommissionStatus::Earned, $commission->refresh()->status);
    }

    public function test_cancelled_lease_deal_and_legacy_statement_do_not_earn_commission(): void
    {
        [$staff, , $broker, , , $property, $deal] = $this->leaseDeal();
        $desk = app(CommissionDesk::class);
        $pipeline = app(SalesPipeline::class);
        $agreement = $desk->propose($deal, $staff, $this->leaseCommissionProposal($broker->id, [
            'protection_ends_at' => now()->addMonths(3),
        ]));
        $desk->accept($agreement, $staff);
        $deal = $pipeline->outcome($deal, SalesOutcome::Cancelled, $staff, $deal->lock_version, 'Lessee withdrew before execution.');
        $this->assertNull($desk->eligibleAmount($agreement->refresh(), $deal->refresh()));

        $legacyStaff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $legacyProperty = Property::factory()->published()->listedIn($this->locality())->lease()->create([
            'acreage' => 3,
            'offered_acreage' => 3,
        ]);
        $this->actingAs($legacyStaff)->post(route('staff.properties.leases.store', $legacyProperty), [
            'starts_on' => now()->toDateString(),
            'acreage' => 3,
            'amount' => 50_000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, CommissionAgreement::query()->where('qualifying_event', QualifyingEvent::LeaseConfirmed)->whereNotNull('earned_amount')->count());
        $this->assertNull(PropertyTransaction::query()->where('property_id', $legacyProperty->id)->value('sales_transaction_id'));
    }

    public function test_finance_payable_and_agency_collection_lifecycle_for_lease(): void
    {
        [$staff, $finance, $broker, $lessor, , , $deal] = $this->leaseDeal();
        $commissions = app(CommissionDesk::class);
        $agencyFees = app(AgencyFeeDesk::class);
        $closing = app(ClosingDesk::class);

        $commission = $commissions->propose($deal, $staff, $this->leaseCommissionProposal($broker->id));
        $commissions->accept($commission, $staff);

        $agency = $agencyFees->propose($deal, $staff, $this->leaseAgencyProposal($lessor->id));
        $agencyFees->accept($agency, $staff);

        $deal = $this->prepareLeaseForConfirmation($deal, $staff);
        $closing->confirmLease($deal, $staff, $deal->lock_version);

        $this->actingAs($finance)->post(route('staff.settlements.approve', $commission))->assertRedirect();
        $this->assertSame(CommissionStatus::Payable, $commission->refresh()->status);

        $this->actingAs($finance)->post(route('staff.settlements.initiate', $commission), [
            'reference' => 'LEASE-PAY-1',
            'reconciliation' => PaymentReconciliation::Matched->value,
        ])->assertRedirect();

        $agency->refresh();
        $this->actingAs($finance)->post(route('staff.agency-collections.initiate', $agency), [
            'reference' => 'LEASE-AF-1',
            'amount' => (int) $agency->earned_amount,
            'reconciliation' => PaymentReconciliation::Unmatched->value,
        ])->assertRedirect();

        $receipt = $agency->refresh()->receipts()->first();
        $this->actingAs($finance)->post(route('staff.agency-collections.collect', ['agencyFeeReceipt' => $receipt->id]), [
            'reconciliation' => PaymentReconciliation::Matched->value,
        ])->assertRedirect();
        $this->assertSame(CommissionStatus::Paid, $agency->refresh()->status);
    }

    public function test_unauthorized_user_cannot_confirm_lease(): void
    {
        [, , , , , , $deal] = $this->leaseDeal();
        $viewer = User::factory()->withRoles('buyer')->create();
        $this->actingAs($viewer)->post(route('staff.sales.lease.confirm', $deal), [
            'lock_version' => $deal->lock_version,
        ])->assertForbidden();
    }

    public function test_sale_deal_still_uses_sale_completed_qualifying_event(): void
    {
        $policy = CommissionPolicy::standard();
        $this->assertSame('agreed_sale_price', $policy->calculationBasis()->value);
        $this->assertDatabaseMissing('commission_agreements', [
            'qualifying_event' => QualifyingEvent::LeaseConfirmed->value,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User, 4: User, 5: Property, 6: SalesTransaction}
     */
    private function leaseDeal(): array
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose', 'agency.propose')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance', 'commissions.approve', 'commissions.record', 'agency.record')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $lessor = User::factory()->withRoles('seller')->create();
        $lessee = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->listedIn($this->locality())->lease(PriceBasis::ParcelMonth)->create([
            'acreage' => 2,
            'offered_acreage' => 2,
            'price_amount' => 120_000,
            'submitted_by' => $broker->id,
        ]);
        $introduction = BrokerIntroduction::query()->create([
            'reference' => 'BI-'.fake()->unique()->regexify('[A-Z0-9]{8}'),
            'broker_id' => $broker->id,
            'buyer_id' => $lessee->id,
            'buyer_name' => $lessee->name,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Accepted,
            'introduced_at' => now()->subDay(),
        ]);

        CommissionPolicy::leaseStandard();
        AgencyFeePolicy::query()->where('code', AgencyFeePolicy::LEASE_DEFAULT)->firstOrFail();

        $deal = app(SalesPipeline::class)->openLease($staff, [
            'origin' => 'introduction',
            'property_id' => $property->id,
            'buyer_id' => $lessee->id,
            'broker_introduction_id' => $introduction->id,
            'acreage' => 2,
        ]);

        $deal = app(SalesPipeline::class)->updateLeaseCommercialTerms($deal, $staff, $deal->lock_version, [
            'lease_starts_on' => now()->toDateString(),
            'lease_ends_on' => now()->addYear()->toDateString(),
            'rent_frequency' => RentFrequency::Monthly->value,
            'agreed_rent' => 120_000,
            'lease_premium' => 500_000,
            'acreage' => 2,
        ]);

        return [$staff, $finance, $broker, $lessor, $lessee, $property, $deal];
    }

    private function prepareLeaseForConfirmation(SalesTransaction $deal, User $staff): SalesTransaction
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
            $deal = $pipeline->advance($deal, $stage, $staff, $deal->lock_version);
        }

        $closing->review($deal, $staff, 'authorized', 'Lease confirmation file is complete.');
        foreach (array_keys(ClosingDesk::LEASE_EVIDENCE) as $code) {
            $closing->evidence($deal, $staff, $code, ClosingEvidenceState::Accepted, 'Verified.');
        }

        return $deal->refresh();
    }

    private function completedLeaseDeal(SalesTransaction $deal): SalesTransaction
    {
        $deal->stage = SalesStage::Completed;
        $deal->completed_at = now();
        $deal->deal_kind = DealKind::Lease;
        $deal->save();

        return $deal->refresh();
    }

    private function locality(): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);

        return Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => 'shela-lease'],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leaseCommissionProposal(int $brokerId, array $overrides = []): array
    {
        return array_merge([
            'broker_id' => $brokerId,
            'calculation_basis' => CommissionCalculationBasis::FirstYearRentPercent->value,
            'rate_percent' => 15,
            'funding_party' => 'lessor',
            'protection_ends_at' => now()->addMonths(6)->toDateString(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leaseAgencyProposal(int $sellerId, array $overrides = []): array
    {
        return array_merge([
            'seller_id' => $sellerId,
            'calculation_basis' => FeeCalculationBasis::FirstYearRentPercent->value,
            'rate_percent' => 10,
            'fee_payer' => 'lessor',
        ], $overrides);
    }
}
