<x-crud-page title="Commission Policy" heading="Commission Policy">
    <p class="text-sm text-lamu-800/70">Rates stay on each signed agreement. This screen does not set a global commission percentage.</p>

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-lamu-900">
        Missing configuration: commission templates are not a managed contract. Agreement template editing has not been added. Use the agreement recorded on each sale.
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
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

    <div class="mt-4 overflow-x-auto rounded-lg border border-sand-200 bg-white">
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
</x-crud-page>
