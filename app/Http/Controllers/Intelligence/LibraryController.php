<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Services\Intelligence\PriceObservatory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function projects(Request $request): View
    {
        return $this->page($request, 'intelligence.partials.projects', 'Development Tracker', 'Published development records. Draft research is not listed.', [
            'projects' => DevelopmentProject::query()->released()->latest('published_at')->paginate(12),
        ]);
    }

    public function project(Request $request, DevelopmentProject $project): View
    {
        abort_unless($project->isReleased(), 404);
        $project->load(['milestones', 'files', 'county', 'locality']);

        return $this->page($request, 'intelligence.partials.project', $project->name, $project->summary, [
            'project' => $project,
        ]);
    }

    public function imagery(Request $request): View
    {
        return $this->page($request, 'intelligence.partials.imagery', 'Satellite comparison', 'Published before-and-after comparisons. Imagery is shown only when the licence allows redistribution.', [
            'comparisons' => ImageryComparison::query()->released()->latest('published_at')->paginate(12),
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
        return $this->page($request, 'intelligence.partials.planning', 'Planning and zoning', 'Published planning records. Restrictions are part of the Investor Pass.', [
            'records' => PlanningRecord::query()->released()->latest('published_at')->paginate(12),
        ]);
    }

    public function plan(Request $request, PlanningRecord $record): View
    {
        abort_unless($record->isReleased(), 404);

        return $this->page($request, 'intelligence.partials.plan', $record->title, $record->area, [
            'record' => $record,
        ]);
    }

    public function prices(Request $request, PriceObservatory $observatory): View
    {
        return $this->page($request, 'intelligence.partials.prices', 'Land price observatory', 'Asking prices from published listings. These are not achieved sale prices.', [
            'rows' => $observatory->askingByLocality(),
            'sales' => $observatory->verifiedSales(),
        ]);
    }

    public function reports(Request $request): View
    {
        return $this->page($request, 'intelligence.partials.reports', 'Research reports', 'Published research summaries. The full report is part of the Investor Pass.', [
            'reports' => ResearchReport::query()->released()->latest('published_at')->paginate(12),
        ]);
    }

    public function report(Request $request, ResearchReport $report): View
    {
        abort_unless($report->isReleased(), 404);
        $report->load(['citations', 'projects', 'localities', 'audits']);

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
