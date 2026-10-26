<?php

namespace App\Services\Sales;

use App\Enums\ClosingEvidenceState;
use App\Enums\CommissionStatus;
use App\Enums\MessageEvent;
use App\Enums\PaymentReconciliation;
use App\Enums\SalesStage;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Notifications\CommissionSettlementNotification;
use App\Notifications\SaleCompletedNotification;
use App\Services\Communications\MessageDesk;
use App\Services\Inventory\PropertyInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClosingDesk
{
    /**
     * @var array<int, string>
     */
    public const EVIDENCE = [
        'transfer_instrument' => 'Transfer instrument',
        'payment_proof' => 'Payment proof',
        'rates_clearance' => 'Rates clearance',
        'possession' => 'Possession note',
    ];

    public function __construct(
        private SalesPipeline $pipeline,
        private CommissionDesk $commissions,
        private AgencyFeeDesk $agencyFees,
        private PropertyInventory $inventory,
    ) {}

    public function seedEvidence(SalesTransaction $transaction): void
    {
        foreach (self::EVIDENCE as $code => $label) {
            $transaction->evidence()->firstOrCreate(
                ['code' => $code],
                ['state' => ClosingEvidenceState::Missing, 'note' => $label],
            );
        }
    }

    public function review(SalesTransaction $transaction, User $actor, string $decision, string $reason): void
    {
        if (! in_array($decision, ['authorized', 'refused'], true)) {
            throw ValidationException::withMessages(['decision' => 'Authorize or refuse the closing.']);
        }

        if ($transaction->stage !== SalesStage::CompletionPending || $transaction->outcome !== null) {
            throw ValidationException::withMessages([
                'decision' => 'Closing review is available when completion is pending.',
            ]);
        }

        $transaction->closingReviews()->create([
            'reviewer_id' => $actor->id,
            'decision' => $decision,
            'reason' => $reason,
            'reviewed_at' => now(),
        ]);
        $this->pipeline->audit($transaction, $actor, 'closing_'.$decision, ['reason' => $reason]);
    }

    public function evidence(SalesTransaction $transaction, User $actor, string $code, ClosingEvidenceState $state, ?string $note): void
    {
        if (! array_key_exists($code, self::EVIDENCE)) {
            throw ValidationException::withMessages(['code' => 'Choose a required closing document.']);
        }

        $this->seedEvidence($transaction);
        $item = $transaction->evidence()->where('code', $code)->firstOrFail();
        $item->state = $state;
        $item->note = $note ?: $item->note;
        $item->reviewed_by = $actor->id;
        $item->save();
    }

    public function complete(SalesTransaction $transaction, User $actor, int $version): SalesTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $version): SalesTransaction {
            $locked = SalesTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->lock_version !== $version) {
                throw ValidationException::withMessages([
                    'lock_version' => 'This transaction was updated by someone else. Reload it and try again.',
                ]);
            }

            if ($locked->stage === SalesStage::Completed) {
                throw ValidationException::withMessages(['stage' => 'This sale is already completed.']);
            }

            if ($locked->stage !== SalesStage::CompletionPending || $locked->outcome !== null) {
                throw ValidationException::withMessages([
                    'stage' => 'Completion is only available from completion pending, and offer acceptance is not a completed sale.',
                ]);
            }

            $authorized = $locked->closingReviews()->where('decision', 'authorized')->exists();
            if (! $authorized) {
                throw ValidationException::withMessages(['closing' => 'An authorized closing review is required.']);
            }

            $this->seedEvidence($locked);
            $missing = $locked->evidence()->where('state', '!=', ClosingEvidenceState::Accepted->value)->count();
            if ($missing > 0) {
                throw ValidationException::withMessages(['evidence' => 'Every required closing document must be accepted.']);
            }

            if ($locked->agreed_price === null || $locked->acreage === null) {
                throw ValidationException::withMessages(['agreed_price' => 'Record the agreed price and acreage before completion.']);
            }

            if ($locked->property->isLease()) {
                throw ValidationException::withMessages([
                    'stage' => 'This is a lease listing. Record the confirmed lease on the property instead of closing a sale.',
                ]);
            }

            app(SalesReassessment::class)->blockIfStale($locked);

            $property = $locked->property()->lockForUpdate()->firstOrFail();
            app(SaleModeTerms::class)->validateTransactionAcreage($property, (float) $locked->acreage);

            $this->inventory->closeSale($actor, $property, (float) $locked->acreage, (int) $locked->agreed_price, $locked);

            $locked->stage = SalesStage::Completed;
            $locked->completed_at = now();
            $locked->lock_version = $version + 1;
            $locked->save();

            $this->pipeline->timeline($locked, $actor, 'Sale completed.', true);
            $this->pipeline->audit($locked, $actor, 'sale_completed', [
                'agreed_price' => $locked->agreed_price,
                'acreage' => (string) $locked->acreage,
            ]);

            $this->earnCommissions($locked, $actor);
            $this->earnAgencyFees($locked, $actor);
            $this->announce($locked, MessageEvent::ClosingCompleted, 'The sale is completed.', 'sale:'.$locked->id);

            return $locked->refresh();
        });
    }

    public function approvePayable(CommissionAgreement $agreement, User $actor): CommissionAgreement
    {
        if ($agreement->status !== CommissionStatus::Earned) {
            throw ValidationException::withMessages(['status' => 'Only an earned commission can be approved for payment.']);
        }

        $agreement->status = CommissionStatus::Payable;
        $agreement->save();
        $this->pipeline->audit($agreement->transaction, $actor, 'commission_payable', [
            'amount' => $agreement->earned_amount,
        ], $agreement->id);
        $this->announceBroker($agreement, MessageEvent::CommissionPayable, 'Commission is payable.');

        return $agreement;
    }

    public function initiatePayment(
        CommissionAgreement $agreement,
        User $actor,
        string $reference,
        PaymentReconciliation $reconciliation,
        ?int $amount = null,
    ): CommissionAgreement {
        if ($agreement->status !== CommissionStatus::Payable) {
            throw ValidationException::withMessages(['status' => 'Payment can be initiated only after finance approval.']);
        }

        if (CommissionPayment::query()->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => 'This payment reference was already used.']);
        }

        $payAmount = $amount ?? $agreement->outstandingPayable();

        if ($payAmount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nothing remains payable on this commission.']);
        }

        if ($payAmount > $agreement->outstandingPayable()) {
            throw ValidationException::withMessages(['amount' => 'This amount exceeds the outstanding commission.']);
        }

        DB::transaction(function () use ($agreement, $actor, $reference, $reconciliation, $payAmount): void {
            $locked = CommissionAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();

            $locked->payments()->create([
                'amount' => $payAmount,
                'reference' => $reference,
                'status' => 'initiated',
                'reconciliation' => $reconciliation,
                'initiated_by' => $actor->id,
                'initiated_at' => now(),
            ]);
        });

        $this->pipeline->audit($agreement->transaction, $actor, 'commission_payment_initiated', [
            'reference' => $reference,
            'reconciliation' => $reconciliation->value,
        ], $agreement->id);

        return $agreement->refresh();
    }

    public function recordPayment(
        CommissionAgreement $agreement,
        User $actor,
        PaymentReconciliation $reconciliation,
        ?string $paymentReference = null,
    ): CommissionAgreement {
        if (! $actor->hasPermission('commissions.record')) {
            throw ValidationException::withMessages(['payment' => 'Recording a commission payment requires finance authority.']);
        }

        return DB::transaction(function () use ($agreement, $actor, $reconciliation, $paymentReference): CommissionAgreement {
            $locked = CommissionAgreement::query()->whereKey($agreement->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->payments()
                ->when($paymentReference, fn ($query) => $query->where('reference', $paymentReference))
                ->where('status', 'initiated')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                throw ValidationException::withMessages(['payment' => 'Initiate the payment before recording it as paid.']);
            }

            if ($locked->status !== CommissionStatus::Payable) {
                throw ValidationException::withMessages(['payment' => 'Only payable commissions can be settled.']);
            }

            if ($payment->amount > $locked->outstandingPayable()) {
                throw ValidationException::withMessages(['amount' => 'This payment exceeds the outstanding commission.']);
            }

            $payment->status = 'paid';
            $payment->reconciliation = $reconciliation;
            $payment->recorded_by = $actor->id;
            $payment->paid_at = now();
            $payment->save();

            $locked->paid_amount = (int) $locked->paid_amount + (int) $payment->amount;
            if ($locked->paid_amount >= (int) $locked->earned_amount) {
                $locked->status = CommissionStatus::Paid;
            }
            $locked->save();

            $this->pipeline->audit($locked->transaction, $actor, 'commission_paid', [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'reconciliation' => $reconciliation->value,
            ], $locked->id);

            if ($locked->status === CommissionStatus::Paid) {
                $this->announceBroker($locked, MessageEvent::CommissionPaid, 'Commission has been paid.');
            }

            return $locked;
        });
    }

    private function earnCommissions(SalesTransaction $transaction, User $actor): void
    {
        $agreements = $transaction->agreements()
            ->where('status', CommissionStatus::Accepted)
            ->where('open_claim', 'open')
            ->get();

        foreach ($agreements as $agreement) {
            if ($agreement->earned_amount !== null) {
                continue;
            }

            $amount = $this->commissions->eligibleAmount($agreement, $transaction);
            if ($amount === null || $amount < 0) {
                continue;
            }

            $agreement->status = CommissionStatus::Earned;
            $agreement->earned_amount = $amount;
            $agreement->paid_amount = 0;
            $agreement->save();
            $this->pipeline->audit($transaction, $actor, 'commission_earned', [
                'agreement_id' => $agreement->id,
                'amount' => $amount,
                'agreed_price' => $transaction->agreed_price,
            ], $agreement->id);
        }
    }

    private function earnAgencyFees(SalesTransaction $transaction, User $actor): void
    {
        $agreements = $transaction->agencyFeeAgreements()
            ->where('status', CommissionStatus::Accepted)
            ->where('open_claim', 'open')
            ->get();

        foreach ($agreements as $agreement) {
            if ($agreement->earned_amount !== null) {
                continue;
            }

            $amount = $this->agencyFees->eligibleAmount($agreement, $transaction);

            if ($amount === null || $amount < 0) {
                continue;
            }

            $agreement->status = CommissionStatus::Earned;
            $agreement->earned_amount = $amount;
            $agreement->collected_amount = 0;
            $agreement->save();

            $this->pipeline->audit($transaction, $actor, 'agency_fee_earned', [
                'agreement_id' => $agreement->id,
                'amount' => $amount,
                'agreed_price' => $transaction->agreed_price,
            ]);
        }
    }

    private function announce(SalesTransaction $transaction, MessageEvent $event, string $summary, string $key): void
    {
        $transaction->loadMissing(['property.owners.user', 'agreements.broker', 'buyer']);
        $recipients = collect([$transaction->buyer])
            ->merge($transaction->property->owners->map(fn ($owner) => $owner->user))
            ->merge($transaction->agreements->map(fn ($agreement) => $agreement->broker))
            ->filter()
            ->unique('id');

        foreach ($recipients as $user) {
            $notification = new SaleCompletedNotification($transaction);
            $data = $notification->toArray($user);
            app(MessageDesk::class)->send($user, $event, [
                'name' => $user->name,
                'title' => $transaction->property->title,
                'reference' => $transaction->reference,
                'summary' => $summary,
                'url' => $data['url'],
            ], $key, $notification);
        }
    }

    private function announceBroker(CommissionAgreement $agreement, MessageEvent $event, string $summary): void
    {
        $agreement->loadMissing('broker', 'transaction.property');

        if ($agreement->broker === null) {
            return;
        }

        app(MessageDesk::class)->send($agreement->broker, $event, [
            'name' => $agreement->broker->name,
            'title' => $agreement->transaction->property->title,
            'reference' => $agreement->reference,
            'summary' => $summary,
            'url' => route('broker.agreements.show', $agreement),
        ], 'commission:'.$agreement->id.':'.$event->value, $event === MessageEvent::CommissionPaid ? new CommissionSettlementNotification($agreement) : null);
    }

    public function paymentReference(): string
    {
        do {
            $reference = 'CP-'.strtoupper(Str::random(8));
        } while (CommissionPayment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
