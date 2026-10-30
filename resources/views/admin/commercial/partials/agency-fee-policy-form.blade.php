<form method="POST" action="{{ $action }}" class="mt-4 space-y-4 rounded-lg border border-sand-200 bg-white p-5" data-turbo-frame="_top" data-confirm="Save this agency fee template?">
    @csrf
    @method('PUT')
    <label class="block text-sm font-medium text-lamu-900">Policy name
        <input name="name" value="{{ old('name', $policy->name) }}" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium text-lamu-900">Default calculation basis
        <select name="default_calculation_basis" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
            @foreach ($agencyFeeBases as $basis)
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
    <label class="block text-sm font-medium text-lamu-900">Fee payer
        <select name="default_fee_payer" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
            @foreach ($feePayers as $payer)
                <option value="{{ $payer->value }}" @selected(old('default_fee_payer', $policy->default_fee_payer) === $payer->value)>{{ $payer->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium text-lamu-900">Effective from (optional)
        <input type="date" name="effective_from" value="{{ old('effective_from', $policy->effective_from?->toDateString()) }}" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium text-lamu-900">Policy terms
        <textarea name="terms" rows="3" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">{{ old('terms', $policy->terms) }}</textarea>
    </label>
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="apply_defaults" value="1" @checked(old('apply_defaults', $policy->apply_defaults))>
        Auto-fill staff proposal forms when active
    </label>
    <p class="text-sm text-lamu-800/70">{{ $policy->appliesForProposals() ? 'Auto-fill is active now.' : 'Auto-fill is off or not yet effective.' }}</p>
    <button type="submit" class="rounded-md bg-lamu-800 px-4 py-2 text-sm font-medium text-white">Save agency fee template</button>
</form>
