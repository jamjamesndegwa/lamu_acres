<?php

namespace App\Services\Research;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\PublicationDelivery;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;

class PublicationArchive
{
    public function isAvailable(PublicationDelivery $delivery): bool
    {
        $subject = $delivery->subject;

        return is_object($subject)
            && method_exists($subject, 'isReleased')
            && $subject->isReleased();
    }

    public function subjectTitle(PublicationDelivery $delivery): string
    {
        $subject = $delivery->subject;

        if (! is_object($subject)) {
            return 'Publication unavailable';
        }

        return match (true) {
            $subject instanceof ResearchReport => $subject->title,
            $subject instanceof DevelopmentProject => $subject->name,
            $subject instanceof PlanningRecord => $subject->title,
            $subject instanceof ImageryComparison => $subject->area_label,
            default => 'Research update',
        };
    }

    public function categoryLabel(PublicationDelivery $delivery): string
    {
        if ($delivery->assignment !== null) {
            return $delivery->assignment->commitment->label();
        }

        $subject = $delivery->subject;

        return match (true) {
            $subject instanceof DevelopmentProject => 'Infrastructure development',
            $subject instanceof ResearchReport => 'Research report',
            $subject instanceof PlanningRecord => 'Planning & zoning',
            $subject instanceof ImageryComparison => 'Satellite intelligence',
            default => 'Research update',
        };
    }

    public function subjectUrl(PublicationDelivery $delivery): ?string
    {
        if (! $this->isAvailable($delivery)) {
            return null;
        }

        $subject = $delivery->subject;

        if (! is_object($subject)) {
            return null;
        }

        return match (true) {
            $subject instanceof ResearchReport => route('investor.reports.show', $subject),
            $subject instanceof DevelopmentProject => route('investor.projects.show', $subject),
            $subject instanceof PlanningRecord => route('investor.planning.show', $subject),
            $subject instanceof ImageryComparison => route('investor.imagery.show', $subject),
            default => null,
        };
    }

    public function watchLabel(PublicationDelivery $delivery, int $userId): ?string
    {
        $subject = $delivery->subject;

        if (! is_object($subject)) {
            return null;
        }

        if ($subject instanceof DevelopmentProject) {
            $watch = SubscriberWatch::query()
                ->where('user_id', $userId)
                ->where('development_project_id', $subject->id)
                ->with('locality')
                ->first();

            if ($watch !== null) {
                return 'Watched project · '.$subject->name;
            }

            if ($subject->locality_id !== null) {
                $localityWatch = SubscriberWatch::query()
                    ->where('user_id', $userId)
                    ->where('locality_id', $subject->locality_id)
                    ->with('locality')
                    ->first();

                if ($localityWatch?->locality !== null) {
                    return 'Watched location · '.$localityWatch->locality->name;
                }
            }

            return null;
        }

        if ($subject instanceof ResearchReport) {
            $subject->loadMissing('localities');

            $locality = $subject->localities->first();

            if ($locality !== null) {
                $watch = SubscriberWatch::query()
                    ->where('user_id', $userId)
                    ->where('locality_id', $locality->id)
                    ->first();

                if ($watch !== null) {
                    return 'Watched location · '.$locality->name;
                }
            }

            return null;
        }

        if ($subject instanceof PlanningRecord && $subject->locality_id !== null) {
            $watch = SubscriberWatch::query()
                ->where('user_id', $userId)
                ->where('locality_id', $subject->locality_id)
                ->with('locality')
                ->first();

            return $watch?->locality !== null ? 'Watched location · '.$watch->locality->name : null;
        }

        if ($subject instanceof ImageryComparison) {
            $subject->loadMissing(['beforeScene.locality', 'afterScene.locality']);
            $localityId = $subject->beforeScene?->locality_id ?? $subject->afterScene?->locality_id;
            $locality = $subject->beforeScene?->locality ?? $subject->afterScene?->locality;

            if ($localityId !== null) {
                $watch = SubscriberWatch::query()
                    ->where('user_id', $userId)
                    ->where('locality_id', $localityId)
                    ->first();

                if ($watch !== null && $locality !== null) {
                    return 'Watched location · '.$locality->name;
                }
            }
        }

        return null;
    }

    public function sourceDate(PublicationDelivery $delivery): ?string
    {
        $subject = $delivery->subject;

        if (! is_object($subject)) {
            return null;
        }

        return match (true) {
            $subject instanceof ResearchReport => $subject->report_date?->toFormattedDateString()
                ?? $subject->published_at?->toFormattedDateString(),
            $subject instanceof DevelopmentProject => $subject->source_published_on?->toFormattedDateString()
                ?? $subject->published_at?->toFormattedDateString(),
            $subject instanceof PlanningRecord => $subject->effective_on?->toFormattedDateString()
                ?? $subject->published_at?->toFormattedDateString(),
            $subject instanceof ImageryComparison => $subject->afterScene?->acquired_on?->toFormattedDateString()
                ?? $subject->published_at?->toFormattedDateString(),
            default => $delivery->delivered_at?->toFormattedDateString(),
        };
    }
}
