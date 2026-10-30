<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionFunding;
use App\Http\Controllers\Controller;
use App\Models\CommissionAgreement;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\CommissionDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommissionAgreementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        return view('staff.commissions.index', [
            'agreements' => CommissionAgreement::query()->with(['transaction', 'broker', 'property'])->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, SalesTransaction $salesTransaction, CommissionDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.propose'), 403);
        $data = $request->validate([
            'broker_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'buyer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'monthly_rent_multiplier' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'fixed_amount' => ['nullable', 'integer', 'min:0'],
            'calculation_basis' => ['nullable', Rule::enum(CommissionCalculationBasis::class)],
            'funding_party' => ['required', Rule::enum(CommissionFunding::class)],
            'protection_ends_at' => ['required', 'date', 'after:today'],
            'payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);
        $broker = User::query()->findOrFail($data['broker_id']);
        abort_unless($broker->hasRole('broker'), 422);

        $agreement = $desk->propose($salesTransaction, $request->user(), $data);

        return redirect()->route('staff.commissions.show', $agreement)->with('status', 'Commission agreement proposed. It is not earned.');
    }

    public function show(Request $request, CommissionAgreement $commissionAgreement): View
    {
        $this->authorizeView($request);
        $commissionAgreement->load(['transaction.property', 'broker', 'buyer', 'property', 'payment']);
        $versions = CommissionAgreement::query()->where('root_id', $commissionAgreement->root_id)->orderBy('version')->get();

        return view('staff.commissions.show', [
            'agreement' => $commissionAgreement,
            'versions' => $versions,
            'funding' => CommissionFunding::cases(),
        ]);
    }

    public function accept(Request $request, CommissionAgreement $commissionAgreement, CommissionDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.propose'), 403);
        $desk->accept($commissionAgreement, $request->user());

        return back()->with('status', 'Agreement accepted. Acceptance does not earn the commission.');
    }

    public function revise(Request $request, CommissionAgreement $commissionAgreement, CommissionDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.propose'), 403);
        $data = $request->validate([
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'integer', 'min:0'],
            'funding_party' => ['nullable', Rule::enum(CommissionFunding::class)],
            'protection_ends_at' => ['nullable', 'date'],
            'payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);
        $next = $desk->revise($commissionAgreement, $request->user(), $data);

        return redirect()->route('staff.commissions.show', $next)->with('status', 'A new agreement version was proposed.');
    }

    public function dispute(Request $request, CommissionAgreement $commissionAgreement, CommissionDesk $desk): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('commissions.propose') || $request->user()->hasPermission('staff.sales'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $desk->dispute($commissionAgreement, $request->user(), $data['reason']);

        return back()->with('status', 'The commission claim is disputed.');
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermission('staff.sales') || $user->hasPermission('staff.finance') || $user->hasPermission('commissions.propose') || $user->hasPermission('staff.brokerage'),
            403,
        );
    }
}
