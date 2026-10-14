<?php

namespace App\Notifications;

use App\Models\ResearchAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class ResearchPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public ResearchAssignment $assignment, public Model $subject) {}

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
        $title = $this->subject->title ?? $this->subject->area_label ?? $this->assignment->commitment->label();

        return [
            'title' => $this->assignment->commitment->label(),
            'message' => (string) $title,
            'url' => route('investor.archive.index'),
        ];
    }
}
