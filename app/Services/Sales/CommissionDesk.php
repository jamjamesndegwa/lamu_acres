<?php

namespace App\Services\Sales;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionStatus;
use App\Enums\QualifyingEvent;
use App\Enums\SalesStage;
use App\Models\CommissionAgreement;
use App\Models\CommissionPolicy;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommissionDesk
{
    public function __construct(
        private SalesPipeline $pipeline,
        private LeaseCommercialTerms $leaseTerms,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function propose(SalesTransaction $transaction, User $actor, array $data): CommissionAgreement
    {
        $this->guardCommercialPropose($transaction);
        $data = $this->mergeProposalDefaults($transaction, $data);

        $this->guardBrokerAuthority($transaction, (int) $data['broker_id']);
        $this->guardClaim($transaction, (int) $data['broker_id']);

        $basis = $this->resolveProposalBasis($transaction, $data);
        $this->validateProposalInputs($transaction, $basis, $data);

        $qualifyingEvent = $transaction->isLeaseDeal()
            ? QualifyingEvent::LeaseConfirmed
            : QualifyingEvent::SaleCompleted;

        $agreement = $transaction->agreements()->create([
            'reference' => $this->reference(),
            'broker_id' => $data['broker_id'],
            'buyer_id' => $data['buyer_id'] ?? $transaction->buyer_id,
            'property_id' => $transaction->property_id,
            'calculation_basis' => $basis,
            'rate_percent' => $data['rate_percent'] ?? null,
            'monthly_rent_multiplier' => $data['monthly_rent_multiplier'] ?? null,
            'fixed_amount' => $data['fixed_amount'] ?? null,
            'funding_party' => $data['funding_party'],
            'protection_starts_at' => $data['protection_starts_at'] ?? now(),
            'protection_ends_at' => $data['protection_ends_at'],
            'qualifying_event' => $qualifyingEvent,
            'payment_due_days' => $data['payment_due_days'] ?? 14,
            'status' => CommissionStatus::Proposed,
            'version' => 1,
            'open_claim' => 'open',
        ]);
        $agreement->root_id = $agreement->id;
        $agreement->save();

        $this->pipeline->audit($transaction, $actor, 'commission_proposed', [
            'agreement_id' => $agreement->id,
            'status' => CommissionStatus::Proposed->value,
            'calculation_basis' => $basis->value,
        ], $agreement->id);

        return $agreement;
    }

    public function accept(CommissionAgreement $agreement, User $actor): CommissionAgreement
    {
        if ($agreement->status !== CommissionStatus::Proposed) {
            throw ValidationException::withMessages(['status' => 'Only a proposed agreement can be accepted.']);
        }

        $agreement->status = CommissionStatus::Accepted;
        $agreement->accepted_at = now();
        $agreement->accepted_by = $actor->id;
        $agreement->save();

        $this->pipeline->audit($agreement->transaction, $actor, 'commission_accepted', [
            'agreement_id' => $agreement->id,
            'status' => CommissionStatus::Accepted->value,
        ], $agreement->id);

        return $agreement;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function revise(CommissionAgreement $agreement, User $actor, array $data): CommissionAgreement
    {
        if (in_array($agreement->status, [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid, CommissionStatus::Superseded], true)) {
            throw ValidationException::withMessages(['status' => 'This version can no longer be revised.']);
        }

        return DB::transaction(function () use ($agreement, $actor, $data): CommissionAgreement {
            $locked = CommissionAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
            $this->guardClaim($locked->transaction, $locked->broker_id, $locked->id);
            $locked->open_claim = null;
            $locked->status = CommissionStatus::Superseded;
            $locked->save();

            $next = $locked->replicate(['accepted_at', 'accepted_by', 'earned_amount', 'calculation_snapshot', 'dispute_reason']);
            $next->reference = $locked->reference;
            $next->calculation_basis = isset($data['calculation_basis'])
                ? CommissionCalculationBasis::from((string) $data['calculation_basis'])
                : $locked->calculation_basis;
            $next->rate_percent = $data['rate_percent'] ?? $locked->rate_percent;
            $next->monthly_rent_multiplier = $data['monthly_rent_multiplier'] ?? $locked->monthly_rent_multiplier;
            $next->fixed_amount = $data['fixed_amount'] ?? $locked->fixed_amount;
            $next->funding_party = $data['funding_party'] ?? $locked->funding_party;
            $next->protection_ends_at = $data['protection_ends_at'] ?? $locked->protection_ends_at;
            $next->payment_due_days = $data['payment_due_days'] ?? $locked->payment_due_days;
            $next->status = CommissionStatus::Proposed;
            $next->version = $locked->version + 1;
            $next->previous_id = $locked->id;
            $next->root_id = $locked->root_id ?? $locked->id;
            $next->open_claim = 'open';
            $next->save();

            $this->pipeline->audit($locked->transaction, $actor, 'commission_revised', [
                'from' => $locked->id,
                'to' => $next->id,
                'version' => $next->version,
            ], $next->id);

            return $next;
        });
    }

    public function dispute(CommissionAgreement $agreement, User $actor, string $reason): CommissionAgreement
    {
        if (! in_array($agreement->status, [CommissionStatus::Proposed, CommissionStatus::Accepted, CommissionStatus::Earned, CommissionStatus::Payable], true)) {
            throw ValidationException::withMessages(['status' => 'This agreement cannot be disputed from '.$agreement->status->label().'.']);
        }

        $agreement->status = CommissionStatus::Disputed;
        $agreement->dispute_reason = $reason;
        $agreement->save();
        $this->pipeline->audit($agreement->transaction, $actor, 'commission_disputed', [
            'reason' => $reason,
        ], $agreement->id);

        return $agreement;
    }

    public function eligibleAmount(CommissionAgreement $agreement, SalesTransaction $transaction): ?int
    {
        $earning = $this->qualifyForEarning($agreement, $transaction);

        return $earning['amount'] ?? null;
    }

    /**
     * @return array{amount: int, snapshot: array<string, mixed>}|null
     */
    public function qualifyForEarning(CommissionAgreement $agreement, SalesTransaction $transaction): ?array
    {
        if ($agreement->status !== CommissionStatus::Accepted) {
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

        if ($agreement->protection_ends_at !== null && $transaction->completed_at->gt($agreement->protection_ends_at)) {
            return null;
        }

        if ($agreement->protection_starts_at !== null && $transaction->completed_at->lt($agreement->protection_starts_at)) {
            return null;
        }

        $basis = $this->resolveAgreementBasis($agreement);
        $amount = $this->leaseTerms->calculateAmount(
            $basis,
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
            throw ValidationException::withMessages(['outcome' => 'This deal is closed and cannot accept new commission terms.']);
        }
    }

    private function guardBrokerAuthority(SalesTransaction $transaction, int $brokerId): void
    {
        $property = $transaction->property;

        $introduced = $transaction->broker_introduction_id !== null
            && $transaction->introduction()->where('broker_id', $brokerId)->exists();

        $submitted = $property !== null && (int) $property->submitted_by === $brokerId;

        if (! $introduced && ! $submitted) {
            throw ValidationException::withMessages([
                'broker_id' => 'This broker does not have an authorized introduction or listing mandate for this deal.',
            ]);
        }
    }

    private function guardClaim(SalesTransaction $transaction, int $brokerId, ?int $exceptId = null): void
    {
        $existing = CommissionAgreement::query()
            ->where('sales_transaction_id', $transaction->id)
            ->where('broker_id', $brokerId)
            ->where('open_claim', 'open')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'broker_id' => 'This broker already has an open commission claim on the transaction.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveProposalBasis(SalesTransaction $transaction, array $data): CommissionCalculationBasis
    {
        if (filled($data['calculation_basis'] ?? null)) {
            $basis = CommissionCalculationBasis::from((string) $data['calculation_basis']);
        } elseif (filled($data['fixed_amount'] ?? null)) {
            $basis = CommissionCalculationBasis::Fixed;
        } elseif ($transaction->isLeaseDeal()) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Choose how lease commission is calculated.',
            ]);
        } else {
            $basis = CommissionCalculationBasis::AgreedSalePrice;
        }

        if ($transaction->isLeaseDeal() && ! $basis->isLease() && $basis !== CommissionCalculationBasis::Fixed) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Outright sale commission bases cannot be used on a lease deal.',
            ]);
        }

        if (! $transaction->isLeaseDeal() && $basis->isLease()) {
            throw ValidationException::withMessages([
                'calculation_basis' => 'Lease commission bases cannot be used on an outright sale.',
            ]);
        }

        return $basis;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateProposalInputs(SalesTransaction $transaction, CommissionCalculationBasis $basis, array $data): void
    {
        if ($basis === CommissionCalculationBasis::Fixed && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages(['fixed_amount' => 'Record the fixed commission amount.']);
        }

        if (in_array($basis, [CommissionCalculationBasis::AgreedSalePrice, CommissionCalculationBasis::FirstYearRentPercent, CommissionCalculationBasis::LeasePremiumPercent], true)
            && blank($data['rate_percent'] ?? null)) {
            throw ValidationException::withMessages(['rate_percent' => 'Agree a percentage rate.']);
        }

        if ($basis === CommissionCalculationBasis::MonthlyRentMultiple && blank($data['monthly_rent_multiplier'] ?? null)) {
            throw ValidationException::withMessages(['monthly_rent_multiplier' => 'Agree how many months of rent the commission equals.']);
        }

        if ($basis === CommissionCalculationBasis::LeasePremiumPercent && blank($transaction->lease_premium)) {
            throw ValidationException::withMessages(['lease_premium' => 'Record a lease premium on the deal before using a premium-based commission.']);
        }

        if ($transaction->isLeaseDeal() && in_array($basis, [CommissionCalculationBasis::FirstYearRentPercent, CommissionCalculationBasis::MonthlyRentMultiple], true)
            && ($transaction->agreed_rent === null || $transaction->rent_frequency === null)) {
            throw ValidationException::withMessages(['agreed_rent' => 'Record lease rent terms on the deal before proposing rent-based commission.']);
        }

        if (! $transaction->isLeaseDeal() && $basis === CommissionCalculationBasis::AgreedSalePrice
            && blank($data['rate_percent'] ?? null) && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages(['rate_percent' => 'Agree a rate or a fixed amount.']);
        }
    }

    private function resolveAgreementBasis(CommissionAgreement $agreement): CommissionCalculationBasis
    {
        if ($agreement->calculation_basis instanceof CommissionCalculationBasis) {
            return $agreement->calculation_basis;
        }

        if ($agreement->calculation_basis !== null) {
            return CommissionCalculationBasis::from((string) $agreement->calculation_basis);
        }

        if ($agreement->fixed_amount !== null) {
            return CommissionCalculationBasis::Fixed;
        }

        return CommissionCalculationBasis::AgreedSalePrice;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeProposalDefaults(SalesTransaction $transaction, array $data): array
    {
        $policy = CommissionPolicy::forDeal($transaction);

        if (! $policy->apply_defaults) {
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

    private function reference(): string
    {
        do {
            $reference = 'CA-'.strtoupper(Str::random(8));
        } while (CommissionAgreement::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
