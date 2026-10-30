<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ClosingEvidenceState;
use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use App\Services\Sales\ClosingDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClosingController extends Controller
{
    public function review(Request $request, SalesTransaction $salesTransaction, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['authorized', 'refused'])],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);
        $desk->review($salesTransaction, $request->user(), $data['decision'], $data['reason']);

        return back()->with('status', 'Closing review recorded.');
    }

    public function evidence(Request $request, SalesTransaction $salesTransaction, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);
        $data = $request->validate([
            'code' => ['required', Rule::in(array_keys($desk->evidenceCatalog($salesTransaction)))],
            'state' => ['required', Rule::enum(ClosingEvidenceState::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $desk->evidence($salesTransaction, $request->user(), $data['code'], ClosingEvidenceState::from($data['state']), $data['note'] ?? null);

        return back()->with('status', 'Closing evidence updated.');
    }

    public function complete(Request $request, SalesTransaction $salesTransaction, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);
        $data = $request->validate(['lock_version' => ['required', 'integer']]);
        $desk->complete($salesTransaction, $request->user(), (int) $data['lock_version']);

        return back()->with('status', 'Sale completed. Inventory was updated with the transaction.');
    }

    public function confirmLease(Request $request, SalesTransaction $salesTransaction, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);
        $data = $request->validate(['lock_version' => ['required', 'integer']]);
        $desk->confirmLease($salesTransaction, $request->user(), (int) $data['lock_version']);

        return back()->with('status', 'Lease commercially confirmed. Inventory and commercial obligations were updated.');
    }
}
