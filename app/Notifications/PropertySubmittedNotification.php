<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PropertySubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property, public string $message = 'A broker submitted land for review.') {}

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
            'property_id' => $this->property->id,
            'title' => 'Broker listing submission',
            'message' => $this->message,
            'url' => route('staff.listings.show', $this->property),
        ];
    }
}
