<?php

namespace App\Services\Sales;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionStatus;
use App\Enums\FeeCalculationBasis;
use App\Enums\PaymentReconciliation;
use App\Enums\QualifyingEvent;
use App\Enums\SalesStage;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeePolicy;
use App\Models\AgencyFeeReceipt;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Communications\SalesFinanceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencyFeeDesk
{
    public function __construct(
        private SalesPipeline $pipeline,
        private LeaseCommercialTerms $leaseTerms,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function propose(SalesTransaction $transaction, User $actor, array $data): AgencyFeeAgreement
    {
        $this->guardOpenClaim($transaction);
        $this->guardCommercialPropose($transaction);
        $data = $this->mergeProposalDefaults($transaction, $data);

        $basis = $this->resolveProposalBasis($transaction, $data);
        $this->validateProposalInputs($transaction, $basis, $data);

        $qualifyingEvent = $transaction->isLeaseDeal()
            ? QualifyingEvent::LeaseConfirmed
            : QualifyingEvent::SaleCompleted;

        $agreement = $transaction->agencyFeeAgreements()->create([
            'reference' => $this->reference(),
            'property_id' => $transaction->property_id,
            'seller_id' => $data['seller_id'],
            'fee_payer' => $data['fee_payer'],
            'calculation_basis' => $basis,
            'rate_percent' => $data['rate_percent'] ?? null,
            'monthly_rent_multiplier' => $data['monthly_rent_multiplier'] ?? null,
            'fixed_amount' => $data['fixed_amount'] ?? null,
            'qualifying_event' => $qualifyingEvent,
            'effective_from' => $data['effective_from'] ?? now(),
            'effective_until' => $data['effective_until'] ?? null,
            'terms' => $data['terms'] ?? null,
            'status' => CommissionStatus::Proposed,
            'version' => 1,
            'open_claim' => 'open',
        ]);
        $agreement->root_id = $agreement->id;
        $agreement->save();

        $this->pipeline->audit($transaction, $actor, 'agency_fee_proposed', [
            'agreement_id' => $agreement->id,
            'status' => CommissionStatus::Proposed->value,
            'calculation_basis' => $basis->value,
        ]);

        return $agreement;
    }

    public function accept(AgencyFeeAgreement $agreement, User $actor): AgencyFeeAgreement
    {
        if ($agreement->status !== CommissionStatus::Proposed) {
            throw ValidationException::withMessages(['status' => 'Only a proposed agency-fee agreement can be accepted.']);
        }

        $agreement->status = CommissionStatus::Accepted;
        $agreement->accepted_at = now();
        $agreement->accepted_by = $actor->id;
        $agreement->save();

        $this->pipeline->audit($agreement->transaction, $actor, 'agency_fee_accepted', [
            'agreement_id' => $agreement->id,
            'status' => CommissionStatus::Accepted->value,
        ]);

        return $agreement;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function revise(AgencyFeeAgreement $agreement, User $actor, array $data): AgencyFeeAgreement
    {
        if (in_array($agreement->status, [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid, CommissionStatus::Superseded], true)) {
            throw ValidationException::withMessages(['status' => 'This agency-fee version can no longer be revised.']);
        }

        return DB::transaction(function () use ($agreement, $actor, $data): AgencyFeeAgreement {
            $locked = AgencyFeeAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
            $locked->open_claim = null;
            $locked->status = CommissionStatus::Superseded;
            $locked->save();

            $next = $locked->replicate(['accepted_at', 'accepted_by', 'earned_amount', 'collected_amount', 'calculation_snapshot', 'dispute_reason']);
            $next->reference = $locked->reference;
            $next->calculation_basis = isset($data['calculation_basis'])
                ? FeeCalculationBasis::from((string) $data['calculation_basis'])
                : $locked->calculation_basis;
            $next->rate_percent = $data['rate_percent'] ?? $locked->rate_percent;
            $next->monthly_rent_multiplier = $data['monthly_rent_multiplier'] ?? $locked->monthly_rent_multiplier;
            $next->fixed_amount = $data['fixed_amount'] ?? $locked->fixed_amount;
            $next->fee_payer = $data['fee_payer'] ?? $locked->fee_payer;
            $next->terms = $data['terms'] ?? $locked->terms;
            $next->status = CommissionStatus::Proposed;
            $next->version = $locked->version + 1;
            $next->previous_id = $locked->id;
            $next->root_id = $locked->root_id ?? $locked->id;
            $next->open_claim = 'open';
            $next->save();

            $this->pipeline->audit($locked->transaction, $actor, 'agency_fee_revised', [
                'from' => $locked->id,
                'to' => $next->id,
                'version' => $next->version,
            ]);

            return $next;
        });
    }

    public function eligibleAmount(AgencyFeeAgreement $agreement, SalesTransaction $transaction): ?int
    {
        $earning = $this->qualifyForEarning($agreement, $transaction);

        return $earning['amount'] ?? null;
    }

    /**
     * @return array{amount: int, snapshot: array<string, mixed>}|null
     */
    public function qualifyForEarning(AgencyFeeAgreement $agreement, SalesTransaction $transaction): ?array
    {
        if ($agreement->status !== CommissionStatus::Accepted) {
            return null;
        }

        if ($agreement->accepted_at === null || $agreement->accepted_by === null) {
            return null;
        }

        if ($agreement->fee_payer === null || $agreement->qualifying_event === null) {
            return null;
        }

        if ($transaction->isLeaseDeal() && $agreement->calculation_basis === null) {
            return null;
        }

        if ($transaction->stage !== SalesStage::Completed || $transaction->completed_at === null) {
            return null;
        }

        if ($transaction->outcome !== null && $transaction->outcome->terminal()) {
            return null;
        }

        $expectedEvent = $transaction->isLeaseDeal()
            ? QualifyingEvent::LeaseConfirmed
            : QualifyingEvent::SaleCompleted;

        if ($agreement->qualifying_event !== $expectedEvent) {
            return null;
        }

        if ($agreement->effective_until !== null && $transaction->completed_at->gt($agreement->effective_until)) {
            return null;
        }

        if ($agreement->effective_from !== null && $transaction->completed_at->lt($agreement->effective_from)) {
            return null;
        }

        $basis = $this->resolveAgreementBasis($agreement);
        $amount = $this->leaseTerms->calculateAmount(
            CommissionCalculationBasis::from($basis->value),
            $transaction,
            $agreement->rate_percent !== null ? (float) $agreement->rate_percent : null,
            $agreement->fixed_amount !== null ? (int) $agreement->fixed_amount : null,
            $agreement->monthly_rent_multiplier !== null ? (float) $agreement->monthly_rent_multiplier : null,
        );

        if ($amount === null || $amount < 0) {
            return null;
        }

        return [
            'amount' => $amount,
            'snapshot' => [
                'calculation_basis' => $basis->value,
                'rate_percent' => $agreement->rate_percent,
                'monthly_rent_multiplier' => $agreement->monthly_rent_multiplier,
                'fixed_amount' => $agreement->fixed_amount,
                'inputs' => $this->leaseTerms->calculationInputs($transaction),
                'agreed_price' => $transaction->agreed_price,
                'qualifying_event' => $expectedEvent->value,
            ],
        ];
    }

    private function guardCommercialPropose(SalesTransaction $transaction): void
    {
        if ($transaction->stage === SalesStage::Completed) {
            throw ValidationException::withMessages(['stage' => 'Commercial agreements cannot be proposed on a completed deal.']);
        }

        if ($transaction->outcome !== null && $transaction->outcome->terminal()) {
            throw ValidationException::withMessages(['outcome' => 'This deal is closed and cannot accept new agency-fee terms.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProposalBasis(SalesTransaction $transaction, array $data): FeeCalculationBasis
    {
        if (filled($data['calculation_basis'] ?? null)) {
            $basis = FeeCalculationBasis::from((string) $data['calculation_basis']);
        } elseif (filled($data['fixed_amount'] ?? null)) {
            $basis = FeeCalculationBasis::Fixed;
        } elseif ($transaction->isLeaseDeal()) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Choose how the lease agency fee is calculated.',
            ]);
        } else {
            $basis = FeeCalculationBasis::AgreedSalePrice;
        }

        if ($transaction->isLeaseDeal() && $basis === FeeCalculationBasis::AgreedSalePrice) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Sale-price agency fees cannot be used on a lease deal.',
            ]);
        }

        if (! $transaction->isLeaseDeal() && $basis !== FeeCalculationBasis::AgreedSalePrice && $basis !== FeeCalculationBasis::Fixed) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Lease agency fee bases cannot be used on an outright sale.',
            ]);
        }

        return $basis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateProposalInputs(SalesTransaction $transaction, FeeCalculationBasis $basis, array $data): void
    {
        if ($basis === FeeCalculationBasis::Fixed && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages(['fixed_amount' => 'Record the fixed agency fee amount.']);
        }

        if (in_array($basis, [FeeCalculationBasis::AgreedSalePrice, FeeCalculationBasis::FirstYearRentPercent, FeeCalculationBasis::LeasePremiumPercent], true)
            && blank($data['rate_percent'] ?? null)) {
            throw ValidationException::withMessages(['rate_percent' => 'Agree a percentage rate.']);
        }

        if ($basis === FeeCalculationBasis::MonthlyRentMultiple && blank($data['monthly_rent_multiplier'] ?? null)) {
            throw ValidationException::withMessages(['monthly_rent_multiplier' => 'Agree how many months of rent the agency fee equals.']);
        }

        if ($basis === FeeCalculationBasis::LeasePremiumPercent && blank($transaction->lease_premium)) {
            throw ValidationException::withMessages(['lease_premium' => 'Record a lease premium on the deal before using a premium-based agency fee.']);
        }

        if ($transaction->isLeaseDeal() && in_array($basis, [FeeCalculationBasis::FirstYearRentPercent, FeeCalculationBasis::MonthlyRentMultiple], true)
            && ($transaction->agreed_rent === null || $transaction->rent_frequency === null)) {
            throw ValidationException::withMessages(['agreed_rent' => 'Record lease rent terms on the deal before proposing rent-based agency fees.']);
        }

        if (! $transaction->isLeaseDeal() && $basis === FeeCalculationBasis::AgreedSalePrice
            && blank($data['rate_percent'] ?? null) && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages(['rate_percent' => 'Agree a percentage or a fixed agency fee.']);
        }
    }

    private function resolveAgreementBasis(AgencyFeeAgreement $agreement): FeeCalculationBasis
    {
        if ($agreement->calculation_basis instanceof FeeCalculationBasis) {
            return $agreement->calculation_basis;
        }

        if ($agreement->calculation_basis !== null) {
            return FeeCalculationBasis::from((string) $agreement->calculation_basis);
        }

        return FeeCalculationBasis::AgreedSalePrice;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeProposalDefaults(SalesTransaction $transaction, array $data): array
    {
        $code = $transaction->isLeaseDeal() ? AgencyFeePolicy::LEASE_DEFAULT : AgencyFeePolicy::DEFAULT;
        $policy = AgencyFeePolicy::query()->where('code', $code)->first();

        if ($policy === null || ! $policy->appliesForProposals()) {
            return $data;
        }

        $defaults = $policy->proposalDefaults();

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $data) || blank($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    public function initiateReceipt(
        AgencyFeeAgreement $agreement,
        User $actor,
        string $reference,
        int $amount,
        PaymentReconciliation $reconciliation,
    ): AgencyFeeAgreement {
        if (! in_array($agreement->status, [CommissionStatus::Earned, CommissionStatus::Payable], true)) {
            throw ValidationException::withMessages(['status' => 'Receipts can be initiated only after the agency fee is earned.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive collection amount.']);
        }

        if ($amount > $agreement->outstandingReceivable()) {
            throw ValidationException::withMessages(['amount' => 'This amount exceeds the outstanding agency fee.']);
        }

        if (AgencyFeeReceipt::query()->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This receipt reference was already used.']);
        }

        DB::transaction(function () use ($agreement, $actor, $reference, $amount, $reconciliation): void {
            $locked = AgencyFeeAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();

            $locked->receipts()->create([
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'initiated',
                'reconciliation' => $reconciliation,
                'initiated_by' => $actor->id,
                'initiated_at' => now(),
            ]);
        });

        $this->pipeline->audit($agreement->transaction, $actor, 'agency_fee_receipt_initiated', [
            'reference' => $reference,
            'amount' => $amount,
        ]);

        return $agreement->refresh();
    }

    public function recordReceipt(AgencyFeeReceipt $receipt, User $actor, PaymentReconciliation $reconciliation): AgencyFeeReceipt
    {
        if (! $actor->hasPermission('agency.record')) {
            throw ValidationException::withMessages(['receipt' => 'Recording agency fee collections requires finance authority.']);
        }

        return DB::transaction(function () use ($receipt, $actor, $reconciliation): AgencyFeeReceipt {
            $lockedReceipt = AgencyFeeReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

            if ($lockedReceipt->status === 'collected') {
                throw ValidationException::withMessages(['receipt' => 'This receipt was already recorded as collected.']);
            }

            if ($lockedReceipt->status !== 'initiated') {
                throw ValidationException::withMessages(['receipt' => 'Initiate the receipt before recording collection.']);
            }

            $agreement = AgencyFeeAgreement::query()->whereKey($lockedReceipt->agency_fee_agreement_id)->lockForUpdate()->firstOrFail();

            if ($lockedReceipt->amount > $agreement->outstandingReceivable()) {
                throw ValidationException::withMessages(['amount' => 'This amount exceeds the outstanding agency fee.']);
            }

            $lockedReceipt->status = 'collected';
            $lockedReceipt->reconciliation = $reconciliation;
            $lockedReceipt->recorded_by = $actor->id;
            $lockedReceipt->collected_at = now();
            $lockedReceipt->save();

            $agreement->collected_amount = (int) $agreement->collected_amount + (int) $lockedReceipt->amount;
            if ($agreement->collected_amount >= (int) $agreement->earned_amount) {
                $agreement->status = CommissionStatus::Paid;
            }
            $agreement->save();

            $this->pipeline->audit($agreement->transaction, $actor, 'agency_fee_collected', [
                'reference' => $lockedReceipt->reference,
                'amount' => $lockedReceipt->amount,
                'reconciliation' => $reconciliation->value,
            ]);

            app(SalesFinanceNotifier::class)->agencyFeeReceiptConfirmed($lockedReceipt);

            return $lockedReceipt;
        });
    }

    private function guardOpenClaim(SalesTransaction $transaction): void
    {
        $exists = AgencyFeeAgreement::query()
            ->where('sales_transaction_id', $transaction->id)
            ->where('open_claim', 'open')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'agreement' => 'This sale already has an open agency-fee agreement.',
            ]);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'AF-'.strtoupper(Str::random(8));
        } while (AgencyFeeAgreement::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
