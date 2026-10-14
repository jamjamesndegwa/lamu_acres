<?php

namespace App\Notifications;

use App\Models\CapabilityApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
        $channels = ['database'];

        if ($notifiable->notify_by_email ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Partner application '.$this->application->status->label())
            ->line($this->application->capability->label().' application: '.$this->application->status->label().'.')
            ->line((string) $this->application->decision_reason);
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
            'message' => $this->application->decision_reason,
            'url' => route('account.applications.index'),
        ];
    }
}
