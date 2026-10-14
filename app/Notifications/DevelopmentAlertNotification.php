<?php

namespace App\Notifications;

use App\Models\DevelopmentProject;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DevelopmentAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public DevelopmentProject $project) {}

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
            'title' => 'Published development update',
            'message' => $this->project->name,
            'summary' => $this->project->summary,
            'url' => route('investor.projects.show', $this->project),
        ];
    }
}
