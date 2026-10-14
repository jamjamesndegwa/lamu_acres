<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CommissionStatus;
use App\Enums\PaymentReconciliation;
use App\Http\Controllers\Controller;
use App\Models\CommissionAgreement;
use App\Services\Sales\ClosingDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.finance'), 403);

        return view('staff.settlements.index', [
            'payable' => CommissionAgreement::query()->with(['broker', 'transaction', 'payment'])->where('status', CommissionStatus::Payable)->latest()->get(),
            'earned' => CommissionAgreement::query()->with(['broker', 'transaction'])->where('status', CommissionStatus::Earned)->latest()->get(),
            'payments' => \App\Models\CommissionPayment::query()->with('agreement.broker')->latest()->paginate(20),
        ]);
    }

    public function approve(Request $request, CommissionAgreement $commissionAgreement, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.approve'), 403);
        $desk->approvePayable($commissionAgreement, $request->user());

        return back()->with('status', 'Commission approved as payable. It is not paid yet.');
    }

    public function initiate(Request $request, CommissionAgreement $commissionAgreement, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.record'), 403);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:80', Rule::unique('commission_payments', 'reference')],
            'reconciliation' => ['required', Rule::enum(PaymentReconciliation::class)],
        ]);
        $desk->initiatePayment($commissionAgreement, $request->user(), $data['reference'], PaymentReconciliation::from($data['reconciliation']));

        return back()->with('status', 'Commission payment initiated.');
    }

    public function pay(Request $request, CommissionAgreement $commissionAgreement, ClosingDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.record'), 403);
        $data = $request->validate([
            'reconciliation' => ['required', Rule::enum(PaymentReconciliation::class)],
        ]);
        $desk->recordPayment($commissionAgreement, $request->user(), PaymentReconciliation::from($data['reconciliation']));

        return back()->with('status', 'Commission payment recorded.');
    }
}
