<?php

namespace App\Services\Subscription;

use App\Enums\MessageEvent;
use App\Enums\SubscriptionStatus;
use App\Models\Entitlement;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\ServiceCredit;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use Illuminate\Validation\ValidationException;

class Memberships
{
    public const UNPAID_NOTE = 'No payment was collected. A finance officer recorded this membership.';

    public function request(User $member): Subscription
    {
        $plan = SubscriptionPlan::investorPass();
        $existing = $this->find($member, $plan);

        if ($existing && in_array($existing->status, [SubscriptionStatus::Pending, SubscriptionStatus::Active], true)) {
            throw ValidationException::withMessages([
                'plan' => 'This account already has a membership request or an active pass.',
            ]);
        }

        $subscription = Subscription::query()->updateOrCreate(
            ['user_id' => $member->id, 'subscription_plan_id' => $plan->id],
            ['status' => SubscriptionStatus::Pending, 'starts_at' => null, 'ends_at' => null, 'cancelled_at' => null],
        );

        $this->event($subscription, $member, 'requested', $plan, 'Membership requested. Payment is not taken in this application.');

        return $subscription;
    }

    public function activate(User $actor, Subscription $subscription): Subscription
    {
        return $this->open($subscription, $actor, 'activated', self::UNPAID_NOTE);
    }

    public function recordVerifiedPayment(Subscription $subscription, string $note, int $amountCents, string $currency): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Active && $subscription->ends_at?->isFuture()) {
            return $this->extend($subscription, $subscription->user, 'renewed', $note, $amountCents, $currency);
        }

        return $this->open($subscription, $subscription->user, 'activated', $note, $amountCents, $currency);
    }

    public function grantCredit(User $actor, Subscription $subscription, int $days, string $reason): Subscription
    {
        abort_unless($actor->hasPermission('staff.finance') || $actor->hasPermission('admin.commercial'), 403);
        abort_unless($subscription->status === SubscriptionStatus::Active && $subscription->ends_at !== null, 422);

        $end = $subscription->ends_at->copy()->addDays($days);
        $subscription->update(['ends_at' => $end]);
        $this->entitlement($subscription->user, Entitlement::STATUS_ACTIVE, $subscription->starts_at, $end);

        ServiceCredit::query()->create([
            'subscription_id' => $subscription->id,
            'actor_id' => $actor->id,
            'days' => $days,
            'reason' => $reason,
        ]);

        $this->event($subscription, $actor, 'credited', $subscription->plan, $days.' days authorized. '.$reason);

        return $subscription;
    }

    public function renew(User $actor, Subscription $subscription): Subscription
    {
        abort_unless($subscription->status === SubscriptionStatus::Active, 422);

        $subscription = $this->extend($subscription, $actor, 'renewed', self::UNPAID_NOTE);
        $this->announce($subscription, MessageEvent::SubscriptionRenewed, 'Your subscription is renewed.');

        return $subscription;
    }

    public function cancel(User $actor, Subscription $subscription): Subscription
    {
        abort_unless($subscription->status === SubscriptionStatus::Active, 422);

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);

        $this->entitlement($subscription->user, 'cancelled', $subscription->starts_at, now());
        $this->event($subscription, $actor, 'cancelled', $subscription->plan, 'Membership cancelled. Premium research is closed.');

        return $subscription;
    }

    public function expireIfDue(?Subscription $subscription): void
    {
        if ($subscription === null || $subscription->status !== SubscriptionStatus::Active || $subscription->ends_at === null || $subscription->ends_at->isFuture()) {
            return;
        }

        $subscription->update(['status' => SubscriptionStatus::Expired]);
        $this->entitlement($subscription->user, 'expired', $subscription->starts_at, $subscription->ends_at);
        $this->event($subscription, $subscription->user, 'expired', $subscription->plan, 'The membership period ended.');
        $this->announce($subscription, MessageEvent::SubscriptionExpired, 'Your subscription has expired.');
    }

    public function find(User $member, ?SubscriptionPlan $plan = null): ?Subscription
    {
        $plan ??= SubscriptionPlan::investorPass();

        return Subscription::query()
            ->where('user_id', $member->id)
            ->where('subscription_plan_id', $plan->id)
            ->first();
    }

    private function entitlement(User $member, string $status, mixed $starts, mixed $ends): void
    {
        $member->entitlements()->updateOrCreate(
            ['code' => Entitlement::INVESTOR_PASS],
            ['status' => $status, 'starts_at' => $starts, 'ends_at' => $ends],
        );
    }

    private function open(Subscription $subscription, User $actor, string $action, string $note, ?int $amountCents = null, ?string $currency = null): Subscription
    {
        $plan = $subscription->plan;
        $start = now();
        $end = $plan->interval === 'month' ? $start->copy()->addMonth() : $start->copy()->addYear();

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'starts_at' => $start,
            'ends_at' => $end,
            'cancelled_at' => null,
        ]);

        $this->entitlement($subscription->user, Entitlement::STATUS_ACTIVE, $start, $end);
        $this->event($subscription, $actor, $action, $plan, $note, $amountCents, $currency);

        return $subscription;
    }

    private function extend(Subscription $subscription, User $actor, string $action, string $note, ?int $amountCents = null, ?string $currency = null): Subscription
    {
        $plan = $subscription->plan;
        $base = $subscription->ends_at && $subscription->ends_at->isFuture() ? $subscription->ends_at->copy() : now();
        $end = $plan->interval === 'month' ? $base->addMonth() : $base->addYear();

        $subscription->update(['ends_at' => $end, 'status' => SubscriptionStatus::Active]);
        $this->entitlement($subscription->user, Entitlement::STATUS_ACTIVE, $subscription->starts_at, $end);
        $this->event($subscription, $actor, $action, $plan, $note, $amountCents, $currency);

        return $subscription;
    }

    private function announce(Subscription $subscription, MessageEvent $event, string $summary): void
    {
        $member = $subscription->user;

        if ($member === null) {
            return;
        }

        $subscription->loadMissing('plan');
        $stamp = $subscription->ends_at?->timestamp ?? $subscription->id;

        app(MessageDesk::class)->send($member, $event, [
            'name' => $member->name,
            'title' => $subscription->plan->name ?? 'Investor Pass',
            'summary' => $summary,
            'url' => route('home'),
        ], 'subscription:'.$subscription->id.':'.$event->value.':'.$stamp);
    }

    private function event(Subscription $subscription, User $actor, string $action, SubscriptionPlan $plan, string $note, ?int $amountCents = null, ?string $currency = null): void
    {
        SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'amount_cents' => $amountCents ?? $plan->amount_cents,
            'currency' => $currency ?? $plan->currency,
            'note' => $note,
        ]);
    }
}
