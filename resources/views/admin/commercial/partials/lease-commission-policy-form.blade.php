<form method="POST" action="{{ $action }}" class="mt-4 max-w-3xl space-y-4 rounded-lg border border-sand-200 bg-white p-5" data-turbo-frame="_top" data-confirm="Save the lease commission template?">
    @csrf
    @method('PUT')
    <label class="block text-sm font-medium text-lamu-900">Policy name
        <input name="name" value="{{ old('name', $policy->name) }}" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium text-lamu-900">Default calculation basis
        <select name="default_calculation_basis" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
            @foreach ($commissionBases as $basis)
                <option value="{{ $basis->value }}" @selected(old('default_calculation_basis', $policy->default_calculation_basis) === $basis->value)>{{ $basis->label() }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block text-sm font-medium text-lamu-900">Default rate (%)
            <input name="default_rate_percent" value="{{ old('default_rate_percent', $policy->default_rate_percent) }}" inputmode="decimal" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium text-lamu-900">Fixed amount (KSh)
            <input name="default_fixed_amount" value="{{ old('default_fixed_amount', $policy->default_fixed_amount) }}" inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium text-lamu-900">Monthly rent multiplier
            <input name="default_monthly_rent_multiplier" value="{{ old('default_monthly_rent_multiplier', $policy->default_monthly_rent_multiplier) }}" inputmode="decimal" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="block text-sm font-medium text-lamu-900">Funding party
            <select name="default_funding_party" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @foreach ($fundingParties as $party)
                    <option value="{{ $party->value }}" @selected(old('default_funding_party', $policy->default_funding_party) === $party->value)>{{ $party->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-lamu-900">Payment due (days)
            <input name="default_payment_due_days" value="{{ old('default_payment_due_days', $policy->default_payment_due_days) }}" required inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium text-lamu-900">Protection (months)
            <input name="default_protection_months" value="{{ old('default_protection_months', $policy->default_protection_months) }}" required inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
    </div>
    <label class="block text-sm font-medium text-lamu-900">Effective from (optional)
        <input type="date" name="effective_from" value="{{ old('effective_from', $policy->effective_from?->toDateString()) }}" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium text-lamu-900">Policy terms
        <textarea name="terms" rows="4" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">{{ old('terms', $policy->terms) }}</textarea>
    </label>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="apply_defaults" value="1" @checked(old('apply_defaults', $policy->apply_defaults))>
        Auto-fill staff proposal forms when active (requires commercial approval)
    </label>
    <p class="text-sm text-lamu-800/70">{{ $policy->appliesForProposals() ? 'Auto-fill is active now.' : 'Auto-fill is off or not yet effective.' }}</p>
    <button type="submit" class="rounded-md bg-lamu-800 px-4 py-2 text-sm font-medium text-white">Save lease commission template</button>
</form>
