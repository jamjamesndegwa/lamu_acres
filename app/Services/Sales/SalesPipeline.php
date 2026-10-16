<?php

namespace App\Services\Sales;

use App\Enums\SalesOrigin;
use App\Enums\SalesOutcome;
use App\Enums\SalesStage;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SaleInstruction;
use App\Models\SalesTransaction;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\TemplatedDeskNotification;
use App\Notifications\WorkAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesPipeline
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function open(User $actor, array $data): SalesTransaction
    {
        $origin = SalesOrigin::from((string) $data['origin']);
        $property = Property::query()->findOrFail($data['property_id']);
        $buyerId = $data['buyer_id'] ?? null;
        $links = $this->links($origin, $property, $buyerId, $data);

        $transaction = SalesTransaction::query()->create([
            'reference' => $this->reference(),
            'property_id' => $property->id,
            'buyer_id' => $links['buyer_id'],
            'origin' => $origin,
            'enquiry_id' => $links['enquiry_id'],
            'sourcing_request_id' => $links['sourcing_request_id'],
            'broker_introduction_id' => $links['broker_introduction_id'],
            'sale_instruction_id' => $links['sale_instruction_id'],
            'assigned_to' => $data['assigned_to'] ?? $actor->id,
            'stage' => SalesStage::Enquiry,
            'acreage' => $data['acreage'] ?? $property->remainingAcreage(),
            'lock_version' => 0,
            'opened_at' => now(),
        ]);

        $this->timeline($transaction, $actor, 'Opened from '.$origin->label().'.');
        $this->audit($transaction, $actor, 'opened', ['origin' => $origin->value]);
        $assignee = User::query()->find($transaction->assigned_to);
        if ($assignee !== null && ! $assignee->is($actor) && $assignee->hasPermission('staff.sales')) {
            $assignee->notify(new WorkAssignedNotification('Sales case', $transaction->id, route('staff.sales.show', $transaction)));
        }

        return $transaction;
    }

    public function assign(SalesTransaction $transaction, User $agent, User $actor, int $version): SalesTransaction
    {
        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($agent, $actor): void {
            $this->guardOpen($locked);
            if ((int) $locked->assigned_to === (int) $agent->id) {
                return;
            }
            $locked->assigned_to = $agent->id;
            if ($agent->hasPermission('staff.sales')) {
                $agent->notify(new WorkAssignedNotification('Sales case', $locked->id, route('staff.sales.show', $locked)));
            }
            $this->timeline($locked, $actor, 'Assigned to '.$agent->name.'.');
            $this->audit($locked, $actor, 'assigned', ['agent_id' => $agent->id]);
        });
    }

    public function advance(SalesTransaction $transaction, SalesStage $stage, User $actor, int $version): SalesTransaction
    {
        if ($stage === SalesStage::Completed) {
            throw ValidationException::withMessages([
                'stage' => 'Offer acceptance and the earlier stages do not complete the sale.',
            ]);
        }

        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($stage, $actor): void {
            $this->guardOpen($locked);

            if (! in_array($stage, $locked->stage->transitions(), true)) {
                throw ValidationException::withMessages([
                    'stage' => 'That stage is not available from '.$locked->stage->label().'.',
                ]);
            }

            $from = $locked->stage;
            $locked->stage = $stage;
            $this->timeline($locked, $actor, 'Stage moved from '.$from->label().' to '.$stage->label().'.');
            $this->audit($locked, $actor, 'stage_changed', ['from' => $from->value, 'to' => $stage->value]);
            $locked->buyer?->notify(new TemplatedDeskNotification(
                'Sale progress updated',
                'Your sale is now '.$stage->label().'. Open the related record for progress.',
                $locked->enquiry_id ? route('buyer.enquiries.show', $locked->enquiry_id) : route('buyer.matches.index'),
            ));
        });
    }

    public function outcome(SalesTransaction $transaction, SalesOutcome $outcome, User $actor, int $version, string $reason): SalesTransaction
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Record why this outcome applies.']);
        }

        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($outcome, $actor, $reason): void {
            if ($locked->stage === SalesStage::Completed) {
                throw ValidationException::withMessages([
                    'outcome' => 'A completed sale is kept on the record.',
                ]);
            }

            if ($locked->outcome !== null && $locked->outcome->terminal()) {
                throw ValidationException::withMessages([
                    'outcome' => 'This transaction is already '.$locked->outcome->label().'.',
                ]);
            }

            if ($outcome === SalesOutcome::Disputed && ! in_array($locked->stage, [
                SalesStage::Negotiation,
                SalesStage::OfferAccepted,
                SalesStage::Contracting,
                SalesStage::CompletionPending,
            ], true)) {
                throw ValidationException::withMessages([
                    'outcome' => 'A dispute can be recorded from negotiation onward.',
                ]);
            }

            $locked->outcome = $outcome;
            $locked->closed_at = $outcome->terminal() ? now() : null;
            $this->timeline($locked, $actor, $outcome->label().': '.$reason);
            $this->audit($locked, $actor, 'outcome_'.$outcome->value, ['reason' => $reason]);
        });
    }

    public function resolveDispute(SalesTransaction $transaction, User $actor, int $version): SalesTransaction
    {
        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($actor): void {
            if ($locked->outcome !== SalesOutcome::Disputed) {
                throw ValidationException::withMessages(['outcome' => 'Only a disputed transaction can be reopened.']);
            }

            $locked->outcome = null;
            $locked->closed_at = null;
            $this->timeline($locked, $actor, 'Dispute resolved. The transaction returned to '.$locked->stage->label().'.');
            $this->audit($locked, $actor, 'dispute_resolved', ['stage' => $locked->stage->value]);
        });
    }

    public function agreePrice(SalesTransaction $transaction, int $price, User $actor, int $version): SalesTransaction
    {
        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($price, $actor): void {
            $this->guardOpen($locked);
            $locked->agreed_price = $price;
            $this->timeline($locked, $actor, 'Agreed price recorded as KSh '.number_format($price).'.');
            $this->audit($locked, $actor, 'price_agreed', ['agreed_price' => $price]);
        });
    }

    /**
     * @param  array{amount: int, seller_response?: string|null, note?: string|null, offer_id?: int|null}  $data
     */
    public function recordOffer(SalesTransaction $transaction, User $actor, int $version, array $data): SalesTransaction
    {
        return $this->mutate($transaction, $version, function (SalesTransaction $locked) use ($actor, $data): void {
            $this->guardOpen($locked);
            $locked->offers()->create([
                'offer_id' => $data['offer_id'] ?? null,
                'amount' => $data['amount'],
                'seller_response' => $data['seller_response'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by' => $actor->id,
            ]);
            $summary = 'Offer of KSh '.number_format((int) $data['amount']).' recorded.';
            if (filled($data['seller_response'] ?? null)) {
                $summary .= ' Seller response: '.$data['seller_response'].'.';
            }
            $this->timeline($locked, $actor, $summary);
            $this->audit($locked, $actor, 'offer_recorded', ['amount' => $data['amount']]);
        });
    }

    public function milestone(SalesTransaction $transaction, User $actor, string $name, ?string $dueOn, ?string $notes): void
    {
        $transaction->milestones()->create([
            'name' => $name,
            'due_on' => $dueOn,
            'notes' => $notes,
        ]);
        $this->timeline($transaction, $actor, 'Milestone: '.$name);
    }

    public function document(SalesTransaction $transaction, User $actor, string $label, string $reference, ?string $note): void
    {
        $transaction->documents()->create([
            'label' => $label,
            'reference' => $reference,
            'note' => $note,
        ]);
        $this->timeline($transaction, $actor, 'Document reference '.$reference.' added.');
    }

    /**
     * @param  callable(SalesTransaction): void  $change
     */
    public function mutate(SalesTransaction $transaction, int $version, callable $change): SalesTransaction
    {
        return DB::transaction(function () use ($transaction, $version, $change): SalesTransaction {
            $locked = SalesTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->lock_version !== $version) {
                throw ValidationException::withMessages([
                    'lock_version' => 'This transaction was updated by someone else. Reload it and try again.',
                ]);
            }

            $change($locked);
            $locked->lock_version = $version + 1;
            $locked->save();

            return $locked;
        });
    }

    public function timeline(SalesTransaction $transaction, ?User $actor, string $summary): void
    {
        $transaction->timeline()->create([
            'actor_id' => $actor?->id,
            'summary' => $summary,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function audit(SalesTransaction $transaction, ?User $actor, string $event, array $payload = [], ?int $agreementId = null): void
    {
        $transaction->audits()->create([
            'commission_agreement_id' => $agreementId,
            'actor_id' => $actor?->id,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    private function guardOpen(SalesTransaction $transaction): void
    {
        if ($transaction->stage === SalesStage::Completed || $transaction->outcome !== null) {
            throw ValidationException::withMessages([
                'stage' => 'This transaction is no longer open.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{buyer_id: int|null, enquiry_id: int|null, sourcing_request_id: int|null, broker_introduction_id: int|null, sale_instruction_id: int|null}
     */
    private function links(SalesOrigin $origin, Property $property, mixed $buyerId, array $data): array
    {
        $links = [
            'buyer_id' => $buyerId ? (int) $buyerId : null,
            'enquiry_id' => null,
            'sourcing_request_id' => null,
            'broker_introduction_id' => null,
            'sale_instruction_id' => null,
        ];

        if ($origin === SalesOrigin::Enquiry) {
            $enquiry = Enquiry::query()->findOrFail($data['enquiry_id'] ?? 0);
            $this->sameProperty($enquiry->property_id, $property->id, 'enquiry_id');
            $links['enquiry_id'] = $enquiry->id;
            $links['buyer_id'] = $enquiry->buyer_id;
        }

        if ($origin === SalesOrigin::Sourcing) {
            $request = SourcingRequest::query()->findOrFail($data['sourcing_request_id'] ?? 0);
            $links['sourcing_request_id'] = $request->id;
            $links['buyer_id'] = $request->buyer_id;
        }

        if ($origin === SalesOrigin::Introduction) {
            $introduction = BrokerIntroduction::query()->findOrFail($data['broker_introduction_id'] ?? 0);
            if ($introduction->property_id !== null) {
                $this->sameProperty($introduction->property_id, $property->id, 'broker_introduction_id');
            }
            $links['broker_introduction_id'] = $introduction->id;
            $links['buyer_id'] = $introduction->buyer_id ?: $links['buyer_id'];
        }

        if ($origin === SalesOrigin::Instruction) {
            $instruction = SaleInstruction::query()->findOrFail($data['sale_instruction_id'] ?? 0);
            $this->sameProperty($instruction->property_id, $property->id, 'sale_instruction_id');
            $links['sale_instruction_id'] = $instruction->id;
        }

        if ($origin !== SalesOrigin::Instruction && $links['buyer_id'] === null) {
            throw ValidationException::withMessages(['buyer_id' => 'Link the buyer who is party to this sale.']);
        }

        return $links;
    }

    private function sameProperty(int $actual, int $expected, string $field): void
    {
        if ($actual !== $expected) {
            throw ValidationException::withMessages([$field => 'That record belongs to a different property.']);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'ST-'.strtoupper(Str::random(8));
        } while (SalesTransaction::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
