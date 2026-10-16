<?php

namespace App\Listeners;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Events\ApplicationDecided;
use App\Notifications\ApplicationOutcomeNotification;
use App\Services\Communications\MessageDesk;

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
        $type = $application->capability === PartnerCapability::Broker && $application->status === CapabilityApplicationStatus::Approved
            ? MessageEvent::BrokerApproved : MessageEvent::ApplicationDecision;
        app(MessageDesk::class)->send($application->user, $type, [
            'name' => $application->user->name, 'title' => $data['title'],
            'summary' => $data['message'], 'url' => $data['url'],
        ], 'application:'.$application->id.':'.count($application->verification_history ?? []), $notification);
    }
}
