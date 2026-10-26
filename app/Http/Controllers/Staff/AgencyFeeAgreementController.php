<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AgencyFeePayer;
use App\Http\Controllers\Controller;
use App\Models\AgencyFeeAgreement;
use App\Models\SalesTransaction;
use App\Services\Sales\AgencyFeeDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyFeeAgreementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        return view('staff.agency-fees.index', [
            'agreements' => AgencyFeeAgreement::query()->with(['transaction', 'property', 'seller'])->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, SalesTransaction $salesTransaction, AgencyFeeDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('agency.propose'), 403);

        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'fee_payer' => ['required', Rule::enum(AgencyFeePayer::class)],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'integer', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'effective_until' => ['nullable', 'date', 'after:today'],
        ]);

        $agreement = $desk->propose($salesTransaction, $request->user(), $data);

        return redirect()->route('staff.agency-fees.show', $agreement)->with('status', 'Agency-fee agreement proposed. It is not earned until the sale completes.');
    }

    public function show(Request $request, AgencyFeeAgreement $agencyFeeAgreement): View
    {
        $this->authorizeView($request);
        $agencyFeeAgreement->load(['transaction.property', 'seller', 'property', 'receipts']);
        $versions = AgencyFeeAgreement::query()->where('root_id', $agencyFeeAgreement->root_id)->orderBy('version')->get();

        return view('staff.agency-fees.show', [
            'agreement' => $agencyFeeAgreement,
            'versions' => $versions,
        ]);
    }

    public function accept(Request $request, AgencyFeeAgreement $agencyFeeAgreement, AgencyFeeDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('agency.propose'), 403);
        $desk->accept($agencyFeeAgreement, $request->user());

        return back()->with('status', 'Agency-fee agreement accepted. Acceptance does not earn the fee.');
    }

    public function revise(Request $request, AgencyFeeAgreement $agencyFeeAgreement, AgencyFeeDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('agency.propose'), 403);
        $data = $request->validate([
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'integer', 'min:0'],
            'fee_payer' => ['nullable', Rule::enum(AgencyFeePayer::class)],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);
        $next = $desk->revise($agencyFeeAgreement, $request->user(), $data);

        return redirect()->route('staff.agency-fees.show', $next)->with('status', 'A new agency-fee version was proposed.');
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('staff.sales') || $user->hasPermission('staff.finance') || $user->hasPermission('agency.propose'),
            403,
        );
    }
}
