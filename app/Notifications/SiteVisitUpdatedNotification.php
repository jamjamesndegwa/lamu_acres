<?php

namespace App\Notifications;

use App\Models\SiteVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SiteVisitUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public SiteVisit $visit, public string $message) {}

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
            'site_visit_id' => $this->visit->id,
            'title' => 'Site visit update',
            'message' => $this->message,
            'url' => ((int) $this->visit->buyer_id === (int) $notifiable->id
                ? route('buyer.matches.show', $this->visit->property_match_id)
                : route('staff.visits.index', ['record' => $this->visit->id]).'#visit-'.$this->visit->id),
        ];
    }
}
