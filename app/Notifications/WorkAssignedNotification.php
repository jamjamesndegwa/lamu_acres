<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $kind, public int $recordId, public string $url) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->kind.' assigned to you', 'message' => 'Open the record to review the work and its current progress.', 'kind' => $this->kind, 'record_id' => $this->recordId, 'url' => $this->url];
    }
}
