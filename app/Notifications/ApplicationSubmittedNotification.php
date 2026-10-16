<?php

namespace App\Notifications;

use App\Models\CapabilityApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public CapabilityApplication $application) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'title' => 'Partner application awaiting review',
            'message' => 'A '.$this->application->capability->label().' application is ready for review.',
            'url' => route('staff.applications.edit', $this->application),
        ];
    }
}
