<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DocumentAccessStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentAccessRequest;
use App\Services\Buyer\InterestWorkflow;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentAccessController extends Controller
{
    public function index(Request $request): View
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

        return ApprovalsReturn::back($request, 'documents', 'Document access updated.');
    }
}
