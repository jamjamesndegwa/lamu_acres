<?php

namespace App\Services\Sales;

use App\Enums\CommissionStatus;
use App\Enums\QualifyingEvent;
use App\Enums\SalesStage;
use App\Models\CommissionAgreement;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommissionDesk
{
    public function __construct(private SalesPipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function propose(SalesTransaction $transaction, User $actor, array $data): CommissionAgreement
    {
        $this->guardClaim($transaction, (int) $data['broker_id']);

        if (blank($data['rate_percent'] ?? null) && blank($data['fixed_amount'] ?? null)) {
            throw ValidationException::withMessages([
                'rate_percent' => 'Agree a rate or a fixed amount.',
            ]);
        }

        $agreement = $transaction->agreements()->create([
            'reference' => $this->reference(),
            'broker_id' => $data['broker_id'],
            'buyer_id' => $data['buyer_id'] ?? $transaction->buyer_id,
            'property_id' => $transaction->property_id,
            'rate_percent' => $data['rate_percent'] ?? null,
            'fixed_amount' => $data['fixed_amount'] ?? null,
            'funding_party' => $data['funding_party'],
            'protection_starts_at' => $data['protection_starts_at'] ?? now(),
            'protection_ends_at' => $data['protection_ends_at'],
            'qualifying_event' => QualifyingEvent::SaleCompleted,
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

            $next = $locked->replicate(['accepted_at', 'accepted_by', 'earned_amount', 'dispute_reason']);
            $next->reference = $locked->reference;
            $next->rate_percent = $data['rate_percent'] ?? $locked->rate_percent;
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
        if ($agreement->status !== CommissionStatus::Accepted) {
            return null;
        }

        if ($transaction->stage !== SalesStage::Completed || $transaction->completed_at === null) {
            return null;
        }

        if ($agreement->qualifying_event !== QualifyingEvent::SaleCompleted) {
            return null;
        }

        if ($agreement->protection_ends_at !== null && $transaction->completed_at->gt($agreement->protection_ends_at)) {
            return null;
        }

        if ($agreement->protection_starts_at !== null && $transaction->completed_at->lt($agreement->protection_starts_at)) {
            return null;
        }

        if ($agreement->fixed_amount !== null) {
            return (int) $agreement->fixed_amount;
        }

        if ($agreement->rate_percent !== null && $transaction->agreed_price !== null) {
            return (int) round(((int) $transaction->agreed_price) * ((float) $agreement->rate_percent) / 100);
        }

        return null;
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

    private function reference(): string
    {
        do {
            $reference = 'CA-'.strtoupper(Str::random(8));
        } while (CommissionAgreement::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
