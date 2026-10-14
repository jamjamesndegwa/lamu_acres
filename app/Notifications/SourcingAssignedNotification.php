<?php

namespace App\Notifications;

use App\Models\SourcingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SourcingAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public SourcingRequest $sourcingRequest) {}

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
            'sourcing_request_id' => $this->sourcingRequest->id,
            'title' => 'Sourcing request assigned',
            'message' => $this->sourcingRequest->reference,
            'url' => route('staff.sourcing.show', $this->sourcingRequest),
        ];
    }
}
