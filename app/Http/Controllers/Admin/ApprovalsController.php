<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Approvals\ApprovalsCentre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalsController extends Controller
{
    public function index(Request $request, ApprovalsCentre $centre): View|RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'tab' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:80'],
        ], [
            'q.max' => 'The search may be at most 80 characters.',
        ]);

        $result = $centre->present(
            $request->user(),
            $data['tab'] ?? 'all',
            trim((string) ($data['q'] ?? '')),
            (string) ($data['status'] ?? ''),
        );

        if ($result['redirect'] !== null) {
            return redirect()
                ->route('admin.approvals.index')
                ->withErrors(['tab' => $result['redirect']]);
        }

        return view('admin.approvals.index', [
            'tab' => $result['tab'],
            'tabs' => $result['tabs'],
            'rows' => $result['rows'],
            'statuses' => $result['statuses'],
            'gaps' => $result['gaps'],
            'search' => $data['q'] ?? '',
            'statusFilter' => $data['status'] ?? '',
        ]);
    }
}
