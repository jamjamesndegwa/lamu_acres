<?php

namespace App\Notifications;

use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ListingStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public Property $property) {}

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
            'title' => 'Listing returned for correction',
            'message' => 'Staff returned your listing to draft. Open it to make the requested corrections.',
            'url' => $this->property->submitterUrl(),
        ];
    }
}
