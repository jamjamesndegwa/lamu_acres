<?php

namespace App\Console\Commands;

use App\Enums\SiteVisitStatus;
use App\Models\SiteVisit;
use App\Services\Communications\BuyerInterestNotifier;
use Illuminate\Console\Command;

class SendSiteVisitReminders extends Command
{
    protected $signature = 'visits:send-reminders';

    protected $description = 'Queue reminder emails for upcoming confirmed site visits';

    public function handle(BuyerInterestNotifier $notifier): int
    {
        $hours = max(1, (int) config('communications.visit_reminder_hours', 24));
        $windowStart = now();
        $windowEnd = now()->addHours($hours);

        SiteVisit::query()
            ->where('status', SiteVisitStatus::Confirmed)
            ->whereNotNull('confirmed_for')
            ->whereBetween('confirmed_for', [$windowStart, $windowEnd])
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->each(fn (SiteVisit $visit) => $notifier->siteVisitReminder($visit));

        return self::SUCCESS;
    }
}
