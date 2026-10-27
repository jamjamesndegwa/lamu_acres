<?php

namespace App\Notifications;

use App\Enums\CapabilityApplicationStatus;
use App\Models\CapabilityApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApplicationOutcomeNotification extends Notification
{
    use Queueable;

    public function __construct(public CapabilityApplication $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'capability' => $this->application->capability->value,
            'status' => $this->application->status->value,
            'title' => $this->application->capability->label().' application '.$this->application->status->label(),
            'message' => 'Your application is '.$this->application->status->label().'. Open your application for the staff decision.',
            'url' => ($this->application->status === CapabilityApplicationStatus::InformationRequired
                ? route('account.applications.create', ['capability' => $this->application->capability->value])
                : route('account.applications.index')),
        ];
    }
}
