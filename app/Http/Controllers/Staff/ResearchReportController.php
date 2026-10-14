<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Concerns\AuthorizesIntelligence;
use App\Models\DevelopmentProject;
use App\Models\ResearchReport;
use App\Services\Intelligence\PublicationDesk;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchReportController extends Controller
{
    use AuthorizesIntelligence;

    public function index(): View
    {
        return view('staff.intelligence.reports.index', [
            'reports' => ResearchReport::query()->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('staff.intelligence.reports.form', [
            'report' => new ResearchReport,
            'projects' => DevelopmentProject::query()->orderBy('name')->get(['id', 'name']),
            'localities' => $this->localities(),
        ]);
    }

    public function store(Request $request, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $data = $this->validated($request);
        $report = ResearchReport::query()->create([
            'reference' => $desk->reference('RP', ResearchReport::class),
            'slug' => $desk->slug($data['title'], ResearchReport::class),
            'title' => $data['title'],
            'summary' => $data['summary'],
            'body' => $data['body'],
            'report_date' => $data['report_date'] ?? null,
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
        ]);
        $this->sync($report, $data);
        $desk->audit($report, $request->user(), 'created', null, PublicationStatus::Draft);

        return redirect()->route('staff.reports.show', $report)->with('status', 'Report draft saved.');
    }

    public function show(ResearchReport $report): View
    {
        $report->load(['citations', 'projects', 'localities', 'audits.actor']);

        return view('staff.intelligence.reports.show', ['report' => $report]);
    }

    public function edit(ResearchReport $report): View
    {
        $report->load(['citations', 'projects', 'localities']);

        return view('staff.intelligence.reports.form', [
            'report' => $report,
            'projects' => DevelopmentProject::query()->orderBy('name')->get(['id', 'name']),
            'localities' => $this->localities(),
        ]);
    }

    public function update(Request $request, ResearchReport $report, PublicationDesk $desk): RedirectResponse
    {
        $this->guardEdit($request, $report);
        $data = $this->validated($request);
        $report->update([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'body' => $data['body'],
            'report_date' => $data['report_date'] ?? null,
        ]);
        $this->sync($report, $data);

        if ($report->isReleased()) {
            $desk->revise($report, $request->user(), ['title' => $report->title]);
        }

        return redirect()->route('staff.reports.show', $report)->with('status', 'Report updated.');
    }

    public function submit(Request $request, ResearchReport $report, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        abort_unless($report->citations()->exists(), 422);
        $desk->submit($report, $request->user());

        return back()->with('status', 'Report submitted for review.');
    }

    public function publish(Request $request, ResearchReport $report, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        abort_unless($report->citations()->exists(), 422);
        $desk->publish($report, $request->user());

        return ApprovalsReturn::back($request, 'intelligence', 'Report published. The summary is the public preview.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:20000'],
            'report_date' => ['nullable', 'date'],
            'citation_label' => ['required', 'string', 'max:160'],
            'citation_url' => ['nullable', 'url', 'max:500'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:development_projects,id'],
            'locality_ids' => ['nullable', 'array'],
            'locality_ids.*' => ['integer', 'exists:localities,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sync(ResearchReport $report, array $data): void
    {
        $report->citations()->delete();
        $report->citations()->create([
            'label' => $data['citation_label'],
            'url' => $data['citation_url'] ?? null,
        ]);
        $report->projects()->sync($data['project_ids'] ?? []);
        $report->localities()->sync($data['locality_ids'] ?? []);
    }
}
