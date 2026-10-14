<?php

namespace App\Listeners;

use App\Events\ApplicationDecided;
use App\Notifications\ApplicationOutcomeNotification;

class NotifyApplicantOfApplicationDecision
{
    public function handle(ApplicationDecided $event): void
    {
        $event->application->loadMissing('user');

        $event->application->user?->notify(new ApplicationOutcomeNotification($event->application));
    }
}
