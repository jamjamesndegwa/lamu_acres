<?php

namespace App\Notifications;

use App\Models\SourcingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SourcingUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public SourcingRequest $request) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'sourcing_request_id' => $this->request->id,
            'title' => 'Land search '.$this->request->status->label(),
            'message' => 'Staff updated your land assistance request. Open it for the latest progress.',
            'url' => route('buyer.sourcing.show', $this->request),
        ];
    }
}
