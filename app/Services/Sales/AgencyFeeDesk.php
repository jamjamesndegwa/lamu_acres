<?php

namespace App\Services\Sales;

use App\Enums\CommissionStatus;
use App\Enums\FeeCalculationBasis;
use App\Enums\PaymentReconciliation;
use App\Enums\QualifyingEvent;
use App\Enums\SalesStage;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeeReceipt;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgencyFeeDesk
{
    public function __construct(private SalesPipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function propose(SalesTransaction $transaction, User $actor, array $data): AgencyFeeAgreement
    {
        $this->guardOpenClaim($transaction);

        if (blank($data['rate_percent'] ?? null) && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages([
                'rate_percent' => 'Agree a percentage or a fixed agency fee.',
            ]);
        }

        $agreement = $transaction->agencyFeeAgreements()->create([
            'reference' => $this->reference(),
            'property_id' => $transaction->property_id,
            'seller_id' => $data['seller_id'],
            'fee_payer' => $data['fee_payer'],
            'calculation_basis' => FeeCalculationBasis::AgreedSalePrice,
            'rate_percent' => $data['rate_percent'] ?? null,
            'fixed_amount' => $data['fixed_amount'] ?? null,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
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

            $next = $locked->replicate(['accepted_at', 'accepted_by', 'earned_amount', 'collected_amount', 'dispute_reason']);
            $next->reference = $locked->reference;
            $next->rate_percent = $data['rate_percent'] ?? $locked->rate_percent;
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
        if ($agreement->status !== CommissionStatus::Accepted) {
            return null;
        }

        if ($transaction->stage !== SalesStage::Completed || $transaction->completed_at === null) {
            return null;
        }

        if ($agreement->qualifying_event !== QualifyingEvent::SaleCompleted) {
            return null;
        }

        if ($agreement->effective_until !== null && $transaction->completed_at->gt($agreement->effective_until)) {
            return null;
        }

        if ($agreement->effective_from !== null && $transaction->completed_at->lt($agreement->effective_from)) {
            return null;
        }

        if ($transaction->agreed_price === null) {
            return null;
        }

        if ($agreement->fixed_amount !== null) {
            return (int) $agreement->fixed_amount;
        }

        if ($agreement->rate_percent !== null) {
            return (int) round(((int) $transaction->agreed_price) * ((float) $agreement->rate_percent) / 100);
        }

        return null;
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
