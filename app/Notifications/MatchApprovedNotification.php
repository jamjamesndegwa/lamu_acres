<?php

namespace App\Notifications;

use App\Models\PropertyMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MatchApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public PropertyMatch $match) {}

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
        $this->match->loadMissing('property');

        return [
            'property_match_id' => $this->match->id,
            'title' => 'Property added to your shortlist',
            'message' => $this->match->property->title,
            'url' => route('buyer.matches.show', $this->match),
        ];
    }
}
