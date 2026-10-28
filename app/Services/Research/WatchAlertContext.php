<?php

namespace App\Services\Research;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WatchAlertContext
{
    /**
     * @return array{name: string, title: string, reference: string, status: string, watch: string, summary: string, url: string}
     */
    public function forEmail(User $user, Model $subject): array
    {
        return [
            'name' => $user->name,
            'title' => $this->title($subject),
            'reference' => $this->reference($subject),
            'status' => $this->publicationType($subject),
            'watch' => $this->watchLabel($user, $subject) ?? 'Your watchlist',
            'summary' => $this->summary($subject),
            'url' => $this->url($subject),
        ];
    }

    /**
     * @return list<int>
     */
    public function watcherUserIdsForReport(ResearchReport $report): array
    {
        $report->loadMissing(['localities', 'projects']);
        $localityIds = $report->localities->pluck('id');
        $projectIds = $report->projects->pluck('id');

        if ($localityIds->isEmpty() && $projectIds->isEmpty()) {
            return [];
        }

        return SubscriberWatch::query()
            ->where(function ($query) use ($localityIds, $projectIds): void {
                if ($localityIds->isNotEmpty()) {
                    $query->whereIn('locality_id', $localityIds);
                }

                if ($projectIds->isNotEmpty()) {
                    $method = $localityIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('development_project_id', $projectIds);
                }
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    private function title(Model $subject): string
    {
        return match (true) {
            $subject instanceof ResearchReport => $subject->title,
            $subject instanceof DevelopmentProject => $subject->name,
            $subject instanceof PlanningRecord => $subject->title,
            $subject instanceof ImageryComparison => $subject->area_label,
            default => 'Research update',
        };
    }

    private function reference(Model $subject): string
    {
        if (! method_exists($subject, 'getAttribute')) {
            return '';
        }

        $reference = $subject->getAttribute('reference');

        return is_string($reference) ? $reference : '';
    }

    private function publicationType(Model $subject): string
    {
        return match (true) {
            $subject instanceof ResearchReport => 'Research report',
            $subject instanceof DevelopmentProject => 'Infrastructure development record',
            $subject instanceof PlanningRecord => 'Planning & zoning record',
            $subject instanceof ImageryComparison => 'Satellite intelligence comparison',
            default => 'Research update',
        };
    }

    private function summary(Model $subject): string
    {
        $text = match (true) {
            $subject instanceof ResearchReport => $subject->summary ?: 'A published research summary is available in your Investor workspace.',
            $subject instanceof DevelopmentProject => $subject->summary ?: 'A published development summary is available in your Investor workspace.',
            $subject instanceof PlanningRecord => $subject->area ?: 'A published planning record is available in your Investor workspace.',
            $subject instanceof ImageryComparison => $subject->observed_change ?: 'A published imagery comparison is available in your Investor workspace.',
            default => 'A published research update is available in your Investor workspace.',
        };

        return Str::limit(trim(strip_tags($text)), 240);
    }

    private function url(Model $subject): string
    {
        return match (true) {
            $subject instanceof ResearchReport => route('investor.reports.show', $subject),
            $subject instanceof DevelopmentProject => route('investor.projects.show', $subject),
            $subject instanceof PlanningRecord => route('investor.planning.show', $subject),
            $subject instanceof ImageryComparison => route('investor.imagery.show', $subject),
            default => route('investor.archive.index'),
        };
    }

    private function watchLabel(User $user, Model $subject): ?string
    {
        return match (true) {
            $subject instanceof DevelopmentProject => $this->projectWatchLabel($user, $subject),
            $subject instanceof ResearchReport => $this->reportWatchLabel($user, $subject),
            $subject instanceof PlanningRecord => $this->localityWatchLabel($user, $subject->locality_id),
            $subject instanceof ImageryComparison => $this->imageryWatchLabel($user, $subject),
            default => null,
        };
    }

    private function projectWatchLabel(User $user, DevelopmentProject $project): ?string
    {
        if (SubscriberWatch::query()->where('user_id', $user->id)->where('development_project_id', $project->id)->exists()) {
            return 'Project · '.$project->name;
        }

        if ($project->locality_id !== null) {
            $locality = SubscriberWatch::query()
                ->where('user_id', $user->id)
                ->where('locality_id', $project->locality_id)
                ->with('locality')
                ->first()?->locality;

            if ($locality !== null) {
                return 'Location · '.$locality->name;
            }
        }

        return null;
    }

    private function reportWatchLabel(User $user, ResearchReport $report): ?string
    {
        $report->loadMissing(['localities', 'projects']);

        $localityWatch = SubscriberWatch::query()
            ->where('user_id', $user->id)
            ->whereIn('locality_id', $report->localities->pluck('id'))
            ->with('locality')
            ->first();

        if ($localityWatch?->locality !== null) {
            return 'Location · '.$localityWatch->locality->name;
        }

        $projectWatch = SubscriberWatch::query()
            ->where('user_id', $user->id)
            ->whereIn('development_project_id', $report->projects->pluck('id'))
            ->with('project')
            ->first();

        if ($projectWatch?->project !== null) {
            return 'Project · '.$projectWatch->project->name;
        }

        return null;
    }

    private function localityWatchLabel(User $user, ?int $localityId): ?string
    {
        if ($localityId === null) {
            return null;
        }

        $locality = SubscriberWatch::query()
            ->where('user_id', $user->id)
            ->where('locality_id', $localityId)
            ->with('locality')
            ->first()?->locality;

        return $locality !== null ? 'Location · '.$locality->name : null;
    }

    private function imageryWatchLabel(User $user, ImageryComparison $comparison): ?string
    {
        $comparison->loadMissing(['beforeScene.locality', 'afterScene.locality', 'project']);
        $localityId = $comparison->beforeScene?->locality_id ?? $comparison->afterScene?->locality_id;

        $localityLabel = $this->localityWatchLabel($user, $localityId);
        if ($localityLabel !== null) {
            return $localityLabel;
        }

        if ($comparison->development_project_id !== null
            && SubscriberWatch::query()->where('user_id', $user->id)->where('development_project_id', $comparison->development_project_id)->exists()) {
            return 'Project · '.($comparison->project?->name ?? 'Watched project');
        }

        return null;
    }
}
