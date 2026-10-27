<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Communications\SalesFinanceNotifier;
use Illuminate\Console\Command;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Queue Investor Pass expiry reminder emails';

    public function handle(SalesFinanceNotifier $notifier): int
    {
        $days = max(1, (int) config('communications.subscription_expiry_reminder_days', 7));
        $windowStart = now();
        $windowEnd = now()->addDays($days);

        Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$windowStart, $windowEnd])
            ->orderBy('id')
            ->each(fn (Subscription $subscription) => $notifier->subscriptionExpiringSoon($subscription));

        return self::SUCCESS;
    }
}
