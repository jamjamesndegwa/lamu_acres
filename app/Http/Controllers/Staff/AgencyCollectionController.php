<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CommissionStatus;
use App\Enums\PaymentReconciliation;
use App\Http\Controllers\Controller;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeeReceipt;
use App\Services\Sales\AgencyFeeDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyCollectionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.finance'), 403);

        return view('staff.agency-collections.index', [
            'receivable' => AgencyFeeAgreement::query()->with(['transaction', 'seller'])->whereIn('status', [
                CommissionStatus::Earned,
                CommissionStatus::Payable,
            ])->latest()->get(),
            'receipts' => AgencyFeeReceipt::query()->with('agreement')->latest()->paginate(20),
        ]);
    }

    public function initiate(Request $request, AgencyFeeAgreement $agencyFeeAgreement, AgencyFeeDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('agency.record'), 403);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:80', Rule::unique('agency_fee_receipts', 'reference')],
            'amount' => ['required', 'integer', 'min:1'],
            'reconciliation' => ['required', Rule::enum(PaymentReconciliation::class)],
        ]);

        $desk->initiateReceipt(
            $agencyFeeAgreement,
            $request->user(),
            $data['reference'],
            (int) $data['amount'],
            PaymentReconciliation::from($data['reconciliation']),
        );

        return back()->with('status', 'Agency fee collection initiated. Initiation is not successful collection.');
    }

    public function collect(Request $request, AgencyFeeReceipt $agencyFeeReceipt, AgencyFeeDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('agency.record'), 403);
        $data = $request->validate([
            'reconciliation' => ['required', Rule::enum(PaymentReconciliation::class)],
        ]);
        $desk->recordReceipt($agencyFeeReceipt, $request->user(), PaymentReconciliation::from($data['reconciliation']));

        return back()->with('status', 'Agency fee collection recorded.');
    }
}
