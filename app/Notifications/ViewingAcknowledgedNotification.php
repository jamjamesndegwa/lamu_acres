<?php

namespace App\Notifications;

use App\Models\SiteVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingAcknowledgedNotification extends Notification
{
    use Queueable;

    public function __construct(public SiteVisit $visit) {}

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
            'title' => 'Viewing acknowledged',
            'message' => 'The buyer confirmed they attended the viewing.',
            'url' => route('staff.visits.index', ['record' => $this->visit->id]).'#visit-'.$this->visit->id,
        ];
    }
}
