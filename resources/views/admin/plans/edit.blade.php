<x-crud-page title="Investor Pass Plans" heading="Investor Pass Plans">
    <p class="text-sm text-lamu-800/70">The published price is configuration. Saving it does not collect a payment and does not change a signed commission agreement.</p>

    <form method="POST" action="{{ route('admin.plans.update') }}" class="mt-4 max-w-xl space-y-4 rounded-lg border border-sand-200 bg-white p-5" data-turbo-frame="_top" data-confirm="Save this plan?">
        @csrf
        @method('PUT')
        <label class="block text-sm">Plan name
            <input name="name" value="{{ old('name', $plan->name) }}" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block text-sm">Current price
                <input name="amount" value="{{ old('amount', number_format($plan->amount_cents / 100, 2, '.', '')) }}" required inputmode="decimal" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @error('amount')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Currency
                <input name="currency" value="{{ old('currency', $plan->currency) }}" required maxlength="3" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2 uppercase">
                @error('currency')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Billing period
                <select name="interval" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                    <option value="year" @selected(old('interval', $plan->interval) === 'year')>Year</option>
                    <option value="month" @selected(old('interval', $plan->interval) === 'month')>Month</option>
                </select>
            </label>
        </div>
        <label class="block text-sm">Membership benefits
            <textarea name="terms" rows="4" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">{{ old('terms', $plan->terms) }}</textarea>
        </label>
        <label class="block text-sm">Payable checkout amount (KES)
            <input name="checkout_amount" value="{{ old('checkout_amount', $plan->checkout_amount) }}" inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
        </label>
        <p class="text-xs text-lamu-800/60">Leave blank to convert the list price to KES automatically at checkout using <code class="text-[11px]">config/subscription.php</code> FX rates. Enter a value here to override conversion.</p>
        <label class="block text-sm">Checkout policy
            <textarea name="checkout_policy" rows="3" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">{{ old('checkout_policy', $plan->checkout_policy) }}</textarea>
            @error('checkout_policy')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))>
            Active plan
        </label>
        <p class="text-sm text-lamu-800/70">Status: {{ $plan->is_active ? 'Active' : 'Inactive' }}. Current published price: {{ $plan->amountLabel() }}.</p>
        <button class="rounded-md bg-lamu-800 px-4 py-2 text-sm text-white">Save plan</button>
    </form>
</x-crud-page>
