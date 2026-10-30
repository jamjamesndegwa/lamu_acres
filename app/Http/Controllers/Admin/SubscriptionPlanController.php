<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgencyFeePayer;
use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionFunding;
use App\Enums\FeeCalculationBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAgencyFeePolicyRequest;
use App\Http\Requests\Admin\UpdateCommissionPolicyRequest;
use App\Http\Requests\Admin\UpdateLeaseCommissionPolicyRequest;
use App\Models\AgencyFeePolicy;
use App\Models\CommissionAgreement;
use App\Models\CommissionPolicy;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'feePayers' => AgencyFeePayer::cases(),
            'commissionBases' => CommissionCalculationBasis::cases(),
            'agencyFeeBases' => FeeCalculationBasis::cases(),
            'canOpenCommissions' => $this->canOpenCommissions($request),
            'saleCommissionPolicy' => CommissionPolicy::standard(),
            'leaseCommissionPolicy' => CommissionPolicy::leaseStandard(),
            'saleAgencyFeePolicy' => AgencyFeePolicy::standard(),
            'leaseAgencyFeePolicy' => AgencyFeePolicy::leaseStandard(),
        ]);
    }

    public function updateCommissions(UpdateCommissionPolicyRequest $request): RedirectResponse
    {
        $this->authorizeCommercial($request);

        $policy = CommissionPolicy::standard();
        $before = $policy->only([
            'name',
            'default_calculation_basis',
            'default_rate_percent',
            'default_fixed_amount',
            'default_funding_party',
            'default_payment_due_days',
            'default_protection_months',
            'effective_from',
            'apply_defaults',
        ]);
        $policy->fill([
            'name' => $request->validated('name'),
            'default_calculation_basis' => $request->validated('default_calculation_basis') ?? CommissionCalculationBasis::AgreedSalePrice->value,
            'default_rate_percent' => filled($request->input('default_rate_percent'))
                ? $request->validated('default_rate_percent')
                : null,
            'default_fixed_amount' => filled($request->input('default_fixed_amount'))
                ? $request->validated('default_fixed_amount')
                : null,
            'default_funding_party' => $request->validated('default_funding_party'),
            'default_payment_due_days' => $request->validated('default_payment_due_days'),
            'default_protection_months' => $request->validated('default_protection_months'),
            'effective_from' => $request->validated('effective_from'),
            'terms' => $request->validated('terms'),
            'apply_defaults' => $request->boolean('apply_defaults'),
        ]);
        $policy->save();
        $this->logPolicyChange($request, 'commission_policy.sale', $before, $policy->fresh()->only(array_keys($before)));

        return back()->with('status', 'Outright sale commission policy updated.');
    }

    public function updateLeaseCommissions(UpdateLeaseCommissionPolicyRequest $request): RedirectResponse
    {
        $this->authorizeCommercial($request);

        $policy = CommissionPolicy::leaseStandard();
        $before = $policy->only([
            'name',
            'default_calculation_basis',
            'default_rate_percent',
            'default_fixed_amount',
            'default_monthly_rent_multiplier',
            'default_funding_party',
            'default_payment_due_days',
            'default_protection_months',
            'effective_from',
            'apply_defaults',
        ]);
        $policy->fill([
            'name' => $request->validated('name'),
            'default_calculation_basis' => $request->validated('default_calculation_basis'),
            'default_rate_percent' => filled($request->input('default_rate_percent'))
                ? $request->validated('default_rate_percent')
                : null,
            'default_fixed_amount' => filled($request->input('default_fixed_amount'))
                ? $request->validated('default_fixed_amount')
                : null,
            'default_monthly_rent_multiplier' => filled($request->input('default_monthly_rent_multiplier'))
                ? $request->validated('default_monthly_rent_multiplier')
                : null,
            'default_funding_party' => $request->validated('default_funding_party'),
            'default_payment_due_days' => $request->validated('default_payment_due_days'),
            'default_protection_months' => $request->validated('default_protection_months'),
            'effective_from' => $request->validated('effective_from'),
            'terms' => $request->validated('terms'),
            'apply_defaults' => $request->boolean('apply_defaults'),
        ]);
        $policy->save();
        $this->logPolicyChange($request, 'commission_policy.lease', $before, $policy->fresh()->only(array_keys($before)));

        return back()->with('status', 'Lease commission policy template updated.');
    }

    public function updateAgencyFeePolicy(UpdateAgencyFeePolicyRequest $request, string $code): RedirectResponse
    {
        $this->authorizeCommercial($request);
        abort_unless(in_array($code, [AgencyFeePolicy::DEFAULT, AgencyFeePolicy::LEASE_DEFAULT], true), 404);

        $policy = AgencyFeePolicy::query()->where('code', $code)->firstOrFail();
        $before = $policy->only([
            'name',
            'default_calculation_basis',
            'default_rate_percent',
            'default_fixed_amount',
            'default_monthly_rent_multiplier',
            'default_fee_payer',
            'effective_from',
            'apply_defaults',
        ]);
        $policy->fill([
            'name' => $request->validated('name'),
            'default_calculation_basis' => $request->validated('default_calculation_basis'),
            'default_rate_percent' => filled($request->input('default_rate_percent'))
                ? $request->validated('default_rate_percent')
                : null,
            'default_fixed_amount' => filled($request->input('default_fixed_amount'))
                ? $request->validated('default_fixed_amount')
                : null,
            'default_monthly_rent_multiplier' => filled($request->input('default_monthly_rent_multiplier'))
                ? $request->validated('default_monthly_rent_multiplier')
                : null,
            'default_fee_payer' => $request->validated('default_fee_payer'),
            'effective_from' => $request->validated('effective_from'),
            'terms' => $request->validated('terms'),
            'apply_defaults' => $request->boolean('apply_defaults'),
        ]);
        $policy->save();
        $this->logPolicyChange($request, 'agency_fee_policy.'.$code, $before, $policy->fresh()->only(array_keys($before)));

        return back()->with('status', 'Agency fee policy template updated.');
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

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function logPolicyChange(Request $request, string $event, array $before, array $after): void
    {
        Log::info($event, [
            'actor_id' => $request->user()?->id,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
