<?php

namespace App\Listeners;

use App\Enums\MessageEvent;
use App\Events\PropertyInventoryChanged;
use App\Notifications\ListingStatusNotification;
use App\Notifications\PropertyApprovedNotification;
use App\Services\Communications\MessageDesk;

class NotifyListingSubmitter
{
    public function handle(PropertyInventoryChanged $event): void
    {
        $property = $event->property;
        if ($event->action !== 'status_changed' || $event->fromStatus === $event->toStatus || $property->submitter === null) {
            return;
        }
        if ($event->toStatus === 'published') {
            app(MessageDesk::class)->send($property->submitter, MessageEvent::PropertyApproved, [
                'name' => $property->submitter->name, 'title' => $property->title,
                'reference' => $property->reference, 'summary' => $property->title.' is approved for listing.',
                'url' => $property->submitterUrl(),
            ], 'property:'.$property->id.':'.$property->published_at?->getTimestamp(), new PropertyApprovedNotification($property));
        } elseif ($event->fromStatus === 'review' && $event->toStatus === 'draft') {
            $property->submitter->notify(new ListingStatusNotification($property));
        }
    }
}
