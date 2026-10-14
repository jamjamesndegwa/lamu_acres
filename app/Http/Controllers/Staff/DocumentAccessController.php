<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DocumentAccessStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentAccessRequest;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentAccessController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        abort_unless($request->user()->hasPermission('staff.buyers'), 403);

        $requests = DocumentAccessRequest::query()
            ->with(['buyer', 'property'])
            ->latest()
            ->paginate(20);

        return view('staff.documents.index', ['requests' => $requests]);
    }

    public function update(Request $request, DocumentAccessRequest $documentAccessRequest, InterestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('decide', $documentAccessRequest);

        $data = $request->validate([
            'status' => ['required', Rule::in([DocumentAccessStatus::Approved->value, DocumentAccessStatus::Declined->value])],
        ]);

        $workflow->decideDocuments($documentAccessRequest, DocumentAccessStatus::from($data['status']), $request->user());

        return back()->with('status', 'Document access updated.');
    }
}
