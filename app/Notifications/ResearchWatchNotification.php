<?php

namespace App\Notifications;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class ResearchWatchNotification extends Notification
{
    use Queueable;

    public function __construct(public Model $subject) {}

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
            'title' => $this->title(),
            'message' => $this->message(),
            'summary' => $this->summary(),
            'category' => $this->category(),
            'url' => $this->url(),
        ];
    }

    private function title(): string
    {
        return match (true) {
            $this->subject instanceof ResearchReport => 'Published research report',
            $this->subject instanceof PlanningRecord => 'Published planning record',
            $this->subject instanceof ImageryComparison => 'Published satellite comparison',
            default => 'Research update',
        };
    }

    private function message(): string
    {
        return match (true) {
            $this->subject instanceof ResearchReport => $this->subject->title,
            $this->subject instanceof PlanningRecord => $this->subject->title,
            $this->subject instanceof ImageryComparison => $this->subject->area_label,
            default => 'A watched research record was published.',
        };
    }

    private function summary(): ?string
    {
        return match (true) {
            $this->subject instanceof ResearchReport => $this->subject->summary,
            $this->subject instanceof PlanningRecord => $this->subject->area,
            $this->subject instanceof ImageryComparison => $this->subject->observed_change,
            default => null,
        };
    }

    private function category(): string
    {
        return match (true) {
            $this->subject instanceof ResearchReport => 'report',
            $this->subject instanceof PlanningRecord => 'planning',
            $this->subject instanceof ImageryComparison => 'imagery',
            default => 'research',
        };
    }

    private function url(): string
    {
        return match (true) {
            $this->subject instanceof ResearchReport => route('investor.reports.show', $this->subject),
            $this->subject instanceof PlanningRecord => route('investor.planning.show', $this->subject),
            $this->subject instanceof ImageryComparison => route('investor.imagery.show', $this->subject),
            $this->subject instanceof DevelopmentProject => route('investor.projects.show', $this->subject),
            default => route('investor.archive.index'),
        };
    }
}
