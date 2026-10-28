<?php

namespace App\Http\Controllers\Intelligence;

use App\Enums\DevelopmentCategory;
use App\Enums\DevelopmentProjectStatus;
use App\Enums\PlanningInstrumentStatus;
use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Services\Intelligence\PriceObservatory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function projects(Request $request): View
    {
        $status = DevelopmentProjectStatus::tryFrom((string) $request->query('status', ''));
        $category = DevelopmentCategory::tryFrom((string) $request->query('category', ''));

        $projects = DevelopmentProject::query()
            ->released()
            ->with(['county', 'locality'])
            ->when($status, fn ($query) => $query->where('project_status', $status))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($request->filled('county'), fn ($query) => $query->where('county_id', $request->integer('county')))
            ->when($request->filled('locality'), fn ($query) => $query->where('locality_id', $request->integer('locality')))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return $this->page($request, 'intelligence.partials.projects', 'Development Tracker', 'Published development records. Draft research is not listed.', [
            'projects' => $projects,
            'statuses' => DevelopmentProjectStatus::cases(),
            'categories' => DevelopmentCategory::cases(),
            'counties' => County::query()->active()->orderBy('name')->get(),
            'localities' => Locality::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function project(Request $request, DevelopmentProject $project): View
    {
        abort_unless($project->isReleased(), 404);
        $project->load(['milestones', 'files', 'county', 'locality', 'audits.actor']);

        $relatedReports = ResearchReport::query()
            ->released()
            ->whereHas('projects', fn ($query) => $query->whereKey($project->id))
            ->latest('published_at')
            ->limit(5)
            ->get();

        $relatedComparisons = ImageryComparison::query()
            ->released()
            ->where('development_project_id', $project->id)
            ->latest('published_at')
            ->limit(5)
            ->get();

        return $this->page($request, 'intelligence.partials.project', $project->name, $project->summary, [
            'project' => $project,
            'relatedReports' => $relatedReports,
            'relatedComparisons' => $relatedComparisons,
        ]);
    }

    public function imagery(Request $request): View
    {
        $comparisons = ImageryComparison::query()
            ->released()
            ->with(['beforeScene.county', 'beforeScene.locality', 'afterScene.county', 'afterScene.locality'])
            ->when($request->filled('county'), function ($query) use ($request): void {
                $county = $request->integer('county');
                $query->where(function ($query) use ($county): void {
                    $query->whereHas('beforeScene', fn ($scene) => $scene->where('county_id', $county))
                        ->orWhereHas('afterScene', fn ($scene) => $scene->where('county_id', $county));
                });
            })
            ->when($request->filled('locality'), function ($query) use ($request): void {
                $locality = $request->integer('locality');
                $query->where(function ($query) use ($locality): void {
                    $query->whereHas('beforeScene', fn ($scene) => $scene->where('locality_id', $locality))
                        ->orWhereHas('afterScene', fn ($scene) => $scene->where('locality_id', $locality));
                });
            })
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return $this->page($request, 'intelligence.partials.imagery', 'Satellite & Aerial Imagery', 'Published before-and-after comparisons. Imagery is shown only when the licence allows redistribution.', [
            'comparisons' => $comparisons,
            'counties' => County::query()->active()->orderBy('name')->get(),
            'localities' => Locality::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function comparison(Request $request, ImageryComparison $comparison): View
    {
        abort_unless($comparison->isReleased(), 404);
        $comparison->load(['beforeScene.files', 'afterScene.files', 'project']);

        return $this->page($request, 'intelligence.partials.comparison', $comparison->area_label, 'A published imagery comparison.', [
            'comparison' => $comparison,
        ]);
    }

    public function planning(Request $request): View
    {
        $instrument = PlanningInstrumentStatus::tryFrom((string) $request->query('status', ''));

        $records = PlanningRecord::query()
            ->released()
            ->with(['county', 'locality'])
            ->when($instrument, fn ($query) => $query->where('instrument_status', $instrument))
            ->when($request->filled('county'), fn ($query) => $query->where('county_id', $request->integer('county')))
            ->when($request->filled('locality'), fn ($query) => $query->where('locality_id', $request->integer('locality')))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return $this->page($request, 'intelligence.partials.planning', 'Planning & Zoning', 'Published planning records. Restrictions are part of the Investor Pass.', [
            'records' => $records,
            'statuses' => PlanningInstrumentStatus::cases(),
            'counties' => County::query()->active()->orderBy('name')->get(),
            'localities' => Locality::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function plan(Request $request, PlanningRecord $record): View
    {
        abort_unless($record->isReleased(), 404);
        $record->load(['files', 'county', 'locality']);

        return $this->page($request, 'intelligence.partials.plan', $record->title, $record->area, [
            'record' => $record,
        ]);
    }

    public function prices(Request $request, PriceObservatory $observatory): View
    {
        $rows = $observatory->askingByLocality();

        if ($request->filled('locality')) {
            $locality = $request->integer('locality');
            $rows = $rows->filter(fn (array $row): bool => (int) $row['locality']->id === $locality)->values();
        }

        return $this->page($request, 'intelligence.partials.prices', 'Land Price Observatory', 'Asking prices from published listings. These are not achieved sale prices.', [
            'rows' => $rows,
            'sales' => $observatory->verifiedSales(),
            'localities' => Locality::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function reports(Request $request): View
    {
        return $this->page($request, 'intelligence.partials.reports', 'Research Reports', 'Published research summaries. The full report is part of the Investor Pass.', [
            'reports' => ResearchReport::query()->released()->with(['projects', 'citations', 'localities'])->latest('published_at')->paginate(12),
        ]);
    }

    public function report(Request $request, ResearchReport $report): View
    {
        abort_unless($report->isReleased(), 404);
        $report->load(['citations', 'projects', 'localities', 'audits', 'files']);

        return $this->page($request, 'intelligence.partials.report', $report->title, $report->summary, [
            'report' => $report,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(Request $request, string $partial, string $heading, string $description, array $data): View
    {
        return view('intelligence.page', [
            ...$data,
            'partial' => $partial,
            'heading' => $heading,
            'description' => $description,
            'premium' => $request->user()?->hasActiveInvestorPass() === true
                || $request->user()?->hasPermission('staff.intelligence') === true
                || $request->user()?->hasPermission('intelligence.publish') === true,
            'frame' => $request->routeIs('investor.*') ? 'workspace' : 'public',
        ]);
    }
}
