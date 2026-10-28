<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Communications\ExternalMailRegistry;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

class RecordExternalNotificationMail
{
    public function __construct(private ExternalMailRegistry $registry) {}

    public function handleSending(NotificationSending $event): void
    {
        if ($event->channel !== 'mail' || ! $event->notifiable instanceof User) {
            return;
        }

        $delivery = $this->registry->findForNotification($event->notifiable, $event->notification);

        if ($delivery !== null) {
            $this->registry->markProcessing($delivery);
        }
    }

    public function handleSent(NotificationSent $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $this->registry->markSent($event);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if ($event->channel !== 'mail') {
            return;
        }

        $this->registry->markFailed($event);
    }
}
