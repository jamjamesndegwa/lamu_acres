<?php

namespace App\Services\Intelligence;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LocalityIntelligenceSummary
{
    private const int RELATED_LIMIT = 8;

    public function __construct(private PriceObservatory $observatory) {}

    /**
     * @return array{
     *     price: ?array,
     *     projects_locality: Collection<int, DevelopmentProject>,
     *     projects_county: Collection<int, DevelopmentProject>,
     *     planning_locality: Collection<int, PlanningRecord>,
     *     planning_county: Collection<int, PlanningRecord>,
     *     imagery: Collection<int, ImageryComparison>,
     *     reports: Collection<int, ResearchReport>,
     * }
     */
    public function compose(Property $property): array
    {
        $property->loadMissing(['areaCounty:id,name,slug', 'areaLocality:id,name,slug,county_id']);

        $locality = $property->areaLocality;
        $county = $property->areaCounty;
        $localityId = $locality?->id;
        $countyId = $county?->id;

        $localityProjectIds = $localityId === null
            ? collect()
            : DevelopmentProject::query()
                ->released()
                ->where('locality_id', $localityId)
                ->limit(self::RELATED_LIMIT)
                ->pluck('id');

        return [
            'price' => $this->observatory->askingForLocality($locality),
            'projects_locality' => $this->projectsInLocality($localityId),
            'projects_county' => $this->projectsCountyContext($countyId, $localityId),
            'planning_locality' => $this->planningInLocality($localityId),
            'planning_county' => $this->planningCountyContext($countyId, $localityId),
            'imagery' => $this->imageryForLocality($localityId),
            'reports' => $this->reportsForLocality($localityId, $localityProjectIds),
        ];
    }

    /**
     * @return Collection<int, DevelopmentProject>
     */
    private function projectsInLocality(?int $localityId): Collection
    {
        if ($localityId === null) {
            return collect();
        }

        return DevelopmentProject::query()
            ->released()
            ->with(['county:id,name,slug', 'locality:id,name,slug'])
            ->where('locality_id', $localityId)
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, DevelopmentProject>
     */
    private function projectsCountyContext(?int $countyId, ?int $localityId): Collection
    {
        if ($countyId === null) {
            return collect();
        }

        return DevelopmentProject::query()
            ->released()
            ->with(['county:id,name,slug', 'locality:id,name,slug'])
            ->where('county_id', $countyId)
            ->when($localityId !== null, function (Builder $query) use ($localityId): void {
                $query->where(function (Builder $inner) use ($localityId): void {
                    $inner->whereNull('locality_id')
                        ->orWhere('locality_id', '!=', $localityId);
                });
            })
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, PlanningRecord>
     */
    private function planningInLocality(?int $localityId): Collection
    {
        if ($localityId === null) {
            return collect();
        }

        return PlanningRecord::query()
            ->released()
            ->with(['county:id,name,slug', 'locality:id,name,slug'])
            ->where('locality_id', $localityId)
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, PlanningRecord>
     */
    private function planningCountyContext(?int $countyId, ?int $localityId): Collection
    {
        if ($countyId === null) {
            return collect();
        }

        return PlanningRecord::query()
            ->released()
            ->with(['county:id,name,slug', 'locality:id,name,slug'])
            ->where('county_id', $countyId)
            ->when($localityId !== null, function (Builder $query) use ($localityId): void {
                $query->where(function (Builder $inner) use ($localityId): void {
                    $inner->whereNull('locality_id')
                        ->orWhere('locality_id', '!=', $localityId);
                });
            })
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, ImageryComparison>
     */
    private function imageryForLocality(?int $localityId): Collection
    {
        if ($localityId === null) {
            return collect();
        }

        return ImageryComparison::query()
            ->released()
            ->with([
                'beforeScene:id,locality_id,acquired_on',
                'afterScene:id,locality_id,acquired_on',
                'project:id,slug,name',
            ])
            ->where(function (Builder $query) use ($localityId): void {
                $query->whereHas('beforeScene', fn (Builder $scene) => $scene->released()->where('locality_id', $localityId))
                    ->orWhereHas('afterScene', fn (Builder $scene) => $scene->released()->where('locality_id', $localityId));
            })
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $localityProjectIds
     * @return Collection<int, ResearchReport>
     */
    private function reportsForLocality(?int $localityId, Collection $localityProjectIds): Collection
    {
        if ($localityId === null && $localityProjectIds->isEmpty()) {
            return collect();
        }

        return ResearchReport::query()
            ->released()
            ->with(['localities:id,name,slug', 'projects:id,slug,name'])
            ->where(function (Builder $query) use ($localityId, $localityProjectIds): void {
                if ($localityId !== null) {
                    $query->whereHas('localities', fn (Builder $inner) => $inner->whereKey($localityId));
                }

                if ($localityProjectIds->isNotEmpty()) {
                    $method = $localityId !== null ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('projects', fn (Builder $inner) => $inner->whereIn('development_projects.id', $localityProjectIds));
                }
            })
            ->latest('published_at')
            ->limit(self::RELATED_LIMIT)
            ->get();
    }
}
