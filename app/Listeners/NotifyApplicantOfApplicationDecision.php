<?php

namespace App\Listeners;

use App\Events\ApplicationDecided;
use App\Notifications\ApplicationOutcomeNotification;

class NotifyApplicantOfApplicationDecision
{
    public function handle(ApplicationDecided $event): void
    {
        $event->application->loadMissing('user');

        $application = $event->application;
        if ($application->user === null) {
            return;
        }
        $notification = new ApplicationOutcomeNotification($application);
        $data = $notification->toArray($application->user);
        $type = $application->capability === \App\Enums\PartnerCapability::Broker && $application->status === \App\Enums\CapabilityApplicationStatus::Approved
            ? \App\Enums\MessageEvent::BrokerApproved : \App\Enums\MessageEvent::ApplicationDecision;
        app(\App\Services\Communications\MessageDesk::class)->send($application->user, $type, [
            'name' => $application->user->name, 'title' => $data['title'],
            'summary' => $data['message'], 'url' => $data['url'],
        ], 'application:'.$application->id.':'.count($application->verification_history ?? []), $notification);
    }
}
