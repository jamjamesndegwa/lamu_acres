<?php

namespace App\Services\Intelligence;

use App\Enums\PublicationStatus;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Models\VerifiedSale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PublicationInventory
{
    /**
     * @return array{
     *     environment: string,
     *     generated_at: string,
     *     categories: array<int, array<string, mixed>>
     * }
     */
    public function snapshot(): array
    {
        return [
            'environment' => (string) config('app.env'),
            'generated_at' => now()->toIso8601String(),
            'categories' => [
                $this->category('Infrastructure (development projects)', DevelopmentProject::class, [
                    'source' => fn (DevelopmentProject $row): bool => filled($row->source_name),
                    'verified_on' => fn (DevelopmentProject $row): bool => filled($row->verified_on),
                    'locality' => fn (DevelopmentProject $row): bool => $row->locality_id !== null,
                    'evidence' => fn (DevelopmentProject $row): bool => $row->files()->exists(),
                ]),
                $this->category('Premium research reports', ResearchReport::class, [
                    'source' => fn (ResearchReport $row): bool => $row->citations()->exists(),
                    'report_date' => fn (ResearchReport $row): bool => filled($row->report_date),
                    'locality' => fn (ResearchReport $row): bool => $row->localities()->exists(),
                ]),
                $this->category('Planning and zoning', PlanningRecord::class, [
                    'source' => fn (PlanningRecord $row): bool => filled($row->source_document),
                    'effective_on' => fn (PlanningRecord $row): bool => filled($row->effective_on),
                    'locality' => fn (PlanningRecord $row): bool => $row->locality_id !== null,
                    'document' => fn (PlanningRecord $row): bool => $row->files()->exists(),
                ]),
                $this->category('Satellite imagery scenes', ImageryScene::class, [
                    'source' => fn (ImageryScene $row): bool => filled($row->provider) && filled($row->license_name),
                    'acquired_on' => fn (ImageryScene $row): bool => filled($row->acquired_on),
                    'licence' => fn (ImageryScene $row): bool => filled($row->license_name),
                    'file' => fn (ImageryScene $row): bool => $row->files()->exists(),
                ]),
                $this->category('Satellite comparisons', ImageryComparison::class, [
                    'observation' => fn (ImageryComparison $row): bool => filled($row->observed_change),
                    'scenes' => fn (ImageryComparison $row): bool => $row->before_scene_id !== null && $row->after_scene_id !== null,
                ]),
                $this->category('Verified sales (price intelligence)', VerifiedSale::class, [
                    'source' => fn (VerifiedSale $row): bool => filled($row->source_name),
                    'locality' => fn (VerifiedSale $row): bool => $row->locality_id !== null,
                    'evidence' => fn (VerifiedSale $row): bool => $row->files()->exists(),
                ]),
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, callable(Model): bool>  $completenessChecks
     * @return array<string, mixed>
     */
    private function category(string $label, string $model, array $completenessChecks): array
    {
        $query = $model::query();

        $counts = [
            'draft' => (clone $query)->where('publication_status', PublicationStatus::Draft)->count(),
            'review' => (clone $query)->where('publication_status', PublicationStatus::Review)->count(),
            'released' => (clone $query)->released()->count(),
            'withdrawn' => (clone $query)->where('publication_status', PublicationStatus::Withdrawn)->count(),
        ];

        $lastPublished = $model::query()
            ->released()
            ->max('published_at');

        $releasedRows = $model::query()->released()->get();
        $releasedCount = $releasedRows->count();

        $completeness = [];
        foreach ($completenessChecks as $key => $check) {
            if ($releasedCount === 0) {
                $completeness[$key] = ['complete' => 0, 'total' => 0, 'percent' => null];

                continue;
            }

            $complete = $releasedRows->filter(fn (Model $row): bool => $check($row))->count();
            $completeness[$key] = [
                'complete' => $complete,
                'total' => $releasedCount,
                'percent' => (int) round(($complete / $releasedCount) * 100),
            ];
        }

        return [
            'label' => $label,
            'counts' => $counts,
            'last_published_at' => $lastPublished ? Carbon::parse($lastPublished)->toIso8601String() : null,
            'completeness' => $completeness,
        ];
    }
}
