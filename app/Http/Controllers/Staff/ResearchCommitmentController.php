<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ImageryComparison;
use App\Models\ResearchAssignment;
use App\Models\ResearchReport;
use App\Services\Research\CommitmentDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchCommitmentController extends Controller
{
    public function index(CommitmentDesk $desk): View
    {
        $desk->sync();

        $assignments = ResearchAssignment::query()->orderBy('due_on')->orderBy('commitment')->get();

        return view('staff.research.compliance', [
            'assignments' => $assignments,
            'counts' => [
                'due' => $assignments->where('status', \App\Enums\AssignmentStatus::Due)->count(),
                'overdue' => $assignments->where('status', \App\Enums\AssignmentStatus::Overdue)->count(),
                'published' => $assignments->where('status', \App\Enums\AssignmentStatus::Published)->count(),
                'unavailable' => $assignments->where('status', \App\Enums\AssignmentStatus::Unavailable)->count(),
            ],
        ]);
    }

    public function fulfillReport(Request $request, ResearchAssignment $assignment, CommitmentDesk $desk): RedirectResponse
    {
        $data = $request->validate(['report_id' => ['required', 'integer']]);
        $report = ResearchReport::query()->findOrFail($data['report_id']);
        $desk->fulfillReport($assignment, $report);

        return back()->with('status', 'The published report was filed against the calendar.');
    }

    public function fulfillImagery(Request $request, ResearchAssignment $assignment, CommitmentDesk $desk): RedirectResponse
    {
        $data = $request->validate(['comparison_id' => ['required', 'integer']]);
        $comparison = ImageryComparison::query()->findOrFail($data['comparison_id']);
        $desk->fulfillImagery($assignment, $comparison);

        return back()->with('status', 'The published comparison was filed against the calendar.');
    }
}
