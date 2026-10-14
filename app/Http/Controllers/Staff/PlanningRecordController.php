<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PlanningInstrumentStatus;
use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Concerns\AuthorizesIntelligence;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Services\Intelligence\IntelligenceFiles;
use App\Services\Intelligence\PublicationDesk;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanningRecordController extends Controller
{
    use AuthorizesIntelligence;

    public function index(): View
    {
        return view('staff.intelligence.planning.index', [
            'records' => PlanningRecord::query()->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('staff.intelligence.planning.form', [
            'record' => new PlanningRecord,
            'localities' => $this->localities(),
        ]);
    }

    public function store(Request $request, PublicationDesk $desk, IntelligenceFiles $files): RedirectResponse
    {
        $this->researcher($request);
        $data = $this->validated($request);
        $record = PlanningRecord::query()->create([
            ...$data,
            'reference' => $desk->reference('PL', PlanningRecord::class),
            'slug' => $desk->slug($data['title'], PlanningRecord::class),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('document')) {
            $files->store($record, $request->user(), $request->file('document'), 'source');
        }

        $desk->audit($record, $request->user(), 'created', null, PublicationStatus::Draft);

        return redirect()->route('staff.planning.show', $record)->with('status', 'Planning draft saved.');
    }

    public function show(PlanningRecord $record): View
    {
        $record->load(['files', 'audits.actor', 'locality']);

        return view('staff.intelligence.planning.show', ['record' => $record]);
    }

    public function update(Request $request, PlanningRecord $record, PublicationDesk $desk): RedirectResponse
    {
        $this->guardEdit($request, $record);
        $record->update($this->validated($request));

        if ($record->isReleased()) {
            $desk->revise($record, $request->user(), ['title' => $record->title]);
        }

        return back()->with('status', 'Planning record updated.');
    }

    public function submit(Request $request, PlanningRecord $record, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $desk->submit($record, $request->user());

        return back()->with('status', 'Submitted for review.');
    }

    public function publish(Request $request, PlanningRecord $record, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $desk->publish($record, $request->user());

        return ApprovalsReturn::back($request, 'intelligence', 'Planning record published.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'area' => ['required', 'string', 'max:160'],
            'locality_id' => ['nullable', 'exists:localities,id'],
            'instrument_status' => ['required', Rule::enum(PlanningInstrumentStatus::class)],
            'permitted_uses' => ['nullable', 'string', 'max:5000'],
            'restrictions' => ['nullable', 'string', 'max:5000'],
            'effective_on' => ['nullable', 'date'],
            'source_document' => ['required', 'string', 'max:160'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'map_reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        unset($data['document']);
        $data['county_id'] = empty($data['locality_id']) ? null : Locality::query()->whereKey($data['locality_id'])->value('county_id');

        return $data;
    }
}
