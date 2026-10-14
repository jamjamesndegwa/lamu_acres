<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionFunding;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Models\CommissionAgreement;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorizeCommercial($request);

        return view('admin.plans.edit', [
            'plan' => SubscriptionPlan::investorPass(),
        ]);
    }

    public function commissions(Request $request): View
    {
        $this->authorizeCommercial($request);

        return view('admin.commercial.commissions', [
            'agreements' => CommissionAgreement::query()->with('property')->latest()->limit(12)->get(),
            'fundingParties' => CommissionFunding::cases(),
            'canOpenCommissions' => $this->canOpenCommissions($request),
        ]);
    }

    public function listings(Request $request): View
    {
        $this->authorizeCommercial($request);

        return view('admin.commercial.listings', [
            'listingStatuses' => PropertyStatus::cases(),
            'availability' => PropertyAvailability::cases(),
        ]);
    }

    public function verification(Request $request): View
    {
        $this->authorizeCommercial($request);

        return view('admin.commercial.verification', [
            'verificationLevels' => VerificationLevel::cases(),
            'verificationCategories' => VerificationCategory::cases(),
            'canOpenVerification' => $request->user()->hasPermission('staff.verification'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeCommercial($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', Rule::in(['year', 'month'])],
            'terms' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'checkout_amount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'checkout_policy' => ['nullable', 'required_with:checkout_amount', 'string', 'max:2000'],
        ], [
            'amount.required' => 'Enter the Investor Pass price.',
            'amount.numeric' => 'The Investor Pass price must be a number.',
            'checkout_policy.required_with' => 'State the checkout policy before saving a payable amount.',
        ]);

        $plan = SubscriptionPlan::investorPass();
        $plan->fill([
            'name' => $data['name'],
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'currency' => strtoupper($data['currency']),
            'interval' => $data['interval'],
            'terms' => $data['terms'] ?? '',
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->exists('checkout_amount')) {
            $plan->checkout_amount = $data['checkout_amount'] ?? null;
            $plan->checkout_currency = filled($data['checkout_amount'] ?? null) ? 'KES' : null;
            $plan->checkout_policy = $data['checkout_policy'] ?? null;
        }

        $plan->save();

        return back()->with('status', 'Investor Pass pricing updated.');
    }

    private function authorizeCommercial(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('admin.commercial'), 403);
    }

    private function canOpenCommissions(Request $request): bool
    {
        $user = $request->user();

        return $user->hasPermission('staff.sales')
            || $user->hasPermission('staff.finance')
            || $user->hasPermission('commissions.propose')
            || $user->hasPermission('staff.brokerage');
    }
}
