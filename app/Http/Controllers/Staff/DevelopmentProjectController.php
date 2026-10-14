<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DevelopmentCategory;
use App\Enums\DevelopmentProjectStatus;
use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Concerns\AuthorizesIntelligence;
use App\Models\DevelopmentProject;
use App\Models\Locality;
use App\Services\Intelligence\IntelligenceFiles;
use App\Services\Intelligence\PublicationDesk;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DevelopmentProjectController extends Controller
{
    use AuthorizesIntelligence;

    public function index(): View
    {
        return view('staff.intelligence.projects.index', [
            'projects' => DevelopmentProject::query()->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('staff.intelligence.projects.form', [
            'project' => new DevelopmentProject,
            'localities' => $this->localities(),
        ]);
    }

    public function store(Request $request, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $data = $this->validated($request);

        $project = DevelopmentProject::query()->create([
            ...$data,
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug($data['name'], DevelopmentProject::class),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'boundary_wkt' => $data['boundary_wkt'] ?? null,
        ]);

        $desk->audit($project, $request->user(), 'created', null, PublicationStatus::Draft, 'No milestones or coordinates were added unless the form included them.');

        return redirect()->route('staff.projects.show', $project)->with('status', 'Draft saved. It is not public.');
    }

    public function show(DevelopmentProject $project): View
    {
        $project->load(['milestones', 'files', 'audits.actor', 'county', 'locality']);

        return view('staff.intelligence.projects.show', ['project' => $project]);
    }

    public function edit(DevelopmentProject $project): View
    {
        return view('staff.intelligence.projects.form', [
            'project' => $project,
            'localities' => $this->localities(),
        ]);
    }

    public function update(Request $request, DevelopmentProject $project, PublicationDesk $desk): RedirectResponse
    {
        $this->guardEdit($request, $project);
        $data = $this->validated($request);
        $project->update($data);

        if ($project->isReleased()) {
            $desk->revise($project, $request->user(), $data);
        } else {
            $desk->audit($project, $request->user(), 'updated', $project->publication_status, $project->publication_status);
        }

        return redirect()->route('staff.projects.show', $project)->with('status', 'Project updated.');
    }

    public function submit(Request $request, DevelopmentProject $project, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $desk->submit($project, $request->user());

        return back()->with('status', 'Submitted for editorial review.');
    }

    public function publish(Request $request, DevelopmentProject $project, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $desk->publish($project, $request->user());

        return ApprovalsReturn::back($request, 'intelligence', 'Project published. Premium detail stays behind an active Investor Pass.');
    }

    public function milestone(Request $request, DevelopmentProject $project, PublicationDesk $desk): RedirectResponse
    {
        $this->guardEdit($request, $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'occurred_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'source_name' => ['required', 'string', 'max:160'],
        ]);

        $project->milestones()->create([...$data, 'created_by' => $request->user()->id]);
        $desk->audit($project, $request->user(), 'milestone_added', $project->publication_status, $project->publication_status, $data['title']);

        return back()->with('status', 'Milestone recorded from the source you named.');
    }

    public function evidence(Request $request, DevelopmentProject $project, IntelligenceFiles $files, PublicationDesk $desk): RedirectResponse
    {
        $this->guardEdit($request, $project);
        $request->validate(['evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $files->store($project, $request->user(), $request->file('evidence'), 'evidence');
        $desk->audit($project, $request->user(), 'evidence_added', $project->publication_status, $project->publication_status);

        return back()->with('status', 'Evidence stored.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::enum(DevelopmentCategory::class)],
            'project_status' => ['required', Rule::enum(DevelopmentProjectStatus::class)],
            'coverage' => ['required', 'string', 'max:160'],
            'locality_id' => ['nullable', 'exists:localities,id'],
            'summary' => ['required', 'string', 'max:2000'],
            'research_notes' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'boundary_wkt' => ['nullable', 'string', 'max:5000'],
            'source_name' => ['required', 'string', 'max:160'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'source_published_on' => ['nullable', 'date'],
            'verified_on' => ['nullable', 'date'],
        ]);

        $data['county_id'] = empty($data['locality_id'])
            ? null
            : Locality::query()->whereKey($data['locality_id'])->value('county_id');

        return $data;
    }
}
