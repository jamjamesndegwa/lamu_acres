<x-workspace-layout heading="Investor Pass">
    <p class="mt-3 max-w-3xl text-stone-700">The published price is configuration. Changing it does not collect a payment.</p>
    <form method="POST" action="{{ route('admin.plans.update') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <label class="block text-sm">Name <input name="name" value="{{ old('name', $plan->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Price <input name="amount" value="{{ old('amount', number_format($plan->amount_cents / 100, 2, '.', '')) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Currency <input name="currency" value="{{ old('currency', $plan->currency) }}" maxlength="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Billing period
            <select name="interval" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="year" @selected(old('interval', $plan->interval) === 'year')>Annual</option>
                <option value="month" @selected(old('interval', $plan->interval) === 'month')>Monthly</option>
            </select>
        </label>
        <label class="block text-sm">Membership terms <textarea name="terms" rows="4" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('terms', $plan->terms) }}</textarea></label>
        <label class="block text-sm">Payable KES amount
            <input name="checkout_amount" value="{{ old('checkout_amount', $plan->checkout_amount) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <p class="text-sm text-stone-600">Whole shillings collected by M-Pesa. Leave this empty to keep payments closed. It is not calculated from the list price.</p>
        <label class="block text-sm">Checkout policy <textarea name="checkout_policy" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('checkout_policy', $plan->checkout_policy) }}</textarea></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Plan can be requested</label>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save plan</button>
    </form>
</x-workspace-layout>
