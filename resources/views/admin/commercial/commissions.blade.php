<x-crud-page title="Commission Policy" heading="Commission Policy">
    <p class="text-sm text-lamu-800/70">
        Set the default template used when staff propose a broker commission. Each sale still records its own signed agreement; changing this policy does not rewrite agreements already on file.
    </p>

    <form method="POST" action="{{ route('admin.commercial.commissions.update') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-sand-200 bg-white p-5" data-turbo-frame="_top" data-confirm="Save the commission policy?">
        @csrf
        @method('PUT')
        <label class="block text-sm font-medium text-lamu-900">Policy name
            <input name="name" value="{{ old('name', $policy->name) }}" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
            @error('name')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-lamu-900">Default rate (%)
                <input name="default_rate_percent" value="{{ old('default_rate_percent', $policy->default_rate_percent) }}" inputmode="decimal" placeholder="e.g. 10" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @error('default_rate_percent')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-lamu-900">Or default fixed amount (KSh)
                <input name="default_fixed_amount" value="{{ old('default_fixed_amount', $policy->default_fixed_amount) }}" inputmode="numeric" placeholder="Leave blank for rate only" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @error('default_fixed_amount')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block text-sm font-medium text-lamu-900">Default funding party
                <select name="default_funding_party" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                    @foreach ($fundingParties as $party)
                        <option value="{{ $party->value }}" @selected(old('default_funding_party', $policy->default_funding_party) === $party->value)>{{ $party->label() }}</option>
                    @endforeach
                </select>
                @error('default_funding_party')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-lamu-900">Payment due (days)
                <input name="default_payment_due_days" value="{{ old('default_payment_due_days', $policy->default_payment_due_days) }}" required inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @error('default_payment_due_days')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-lamu-900">Protection period (months)
                <input name="default_protection_months" value="{{ old('default_protection_months', $policy->default_protection_months) }}" required inputmode="numeric" class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">
                @error('default_protection_months')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="block text-sm font-medium text-lamu-900">Policy terms (shown to administrators and prefilled for staff)
            <textarea name="terms" rows="5" required class="mt-1 w-full rounded-md border border-sand-200 px-3 py-2">{{ old('terms', $policy->terms) }}</textarea>
            @error('terms')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="apply_defaults" value="1" @checked(old('apply_defaults', $policy->apply_defaults))>
            Apply these defaults when staff propose a commission
        </label>

        <p class="text-sm text-lamu-800/70">Active summary: {{ $policy->summaryLabel() }}</p>

        <button type="submit" class="rounded-md bg-lamu-800 px-4 py-2 text-sm font-medium text-white">Save commission policy</button>
    </form>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-lamu-900">Supported funding parties</h2>
            <ul class="mt-2 space-y-1 text-sm text-lamu-800/80">
                @foreach ($fundingParties as $party)
                    <li>{{ $party->label() }}</li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-lg border border-sand-200 bg-white p-4 text-sm text-lamu-800/80">
            @if ($canOpenCommissions)
                <a href="{{ route('staff.commissions.index') }}" class="font-semibold text-lamu-800 underline">Open commission management</a>
            @else
                <p>Commission management is on the sales desk. This account is not authorized to open it.</p>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <h2 class="text-sm font-semibold text-lamu-900">Recently recorded agreements</h2>
        <div class="mt-3 overflow-x-auto rounded-lg border border-sand-200 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-sand-50 text-lamu-800/70">
                    <tr>
                        <th class="px-4 py-2 font-medium">Reference</th>
                        <th class="px-4 py-2 font-medium">Recorded arrangement</th>
                        <th class="px-4 py-2 font-medium">Funding</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agreements as $agreement)
                        <tr class="border-t border-sand-200">
                            <td class="px-4 py-2">
                                @if ($canOpenCommissions)
                                    <a href="{{ route('staff.commissions.show', $agreement) }}" class="font-medium text-lamu-800 underline">{{ $agreement->reference }}</a>
                                @else
                                    {{ $agreement->reference }}
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($agreement->rate_percent !== null)
                                    {{ $agreement->rate_percent }}% on this agreement
                                @elseif ($agreement->fixed_amount !== null)
                                    Fixed {{ number_format($agreement->fixed_amount) }} on this agreement
                                @else
                                    Arrangement recorded without a rate
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $agreement->funding_party->label() }}</td>
                            <td class="px-4 py-2">{{ $agreement->status->label() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-lamu-800/60">No commission agreement has been recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-crud-page>
