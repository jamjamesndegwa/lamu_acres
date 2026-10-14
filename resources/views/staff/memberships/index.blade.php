<x-workspace-layout heading="Investor Pass memberships">
    <p class="mt-3 max-w-3xl text-stone-700">Recording a membership does not take a card payment. Access starts only after activation, and it uses the member’s existing account.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Member', 'Status', 'Period', '']">
            @forelse ($subscriptions as $subscription)
                <tr class="border-b border-sand-200">
                    <td class="px-4 py-3">{{ $subscription->user->name }} · {{ $subscription->user->email }}</td>
                    <td class="px-4 py-3">{{ $subscription->status->label() }}</td>
                    <td class="px-4 py-3">{{ $subscription->starts_at?->toFormattedDateString() }} – {{ $subscription->ends_at?->toFormattedDateString() }}</td>
                    <td class="px-4 py-3">
                        @if ($subscription->status === \App\Enums\SubscriptionStatus::Pending)
                            <form method="POST" action="{{ route('staff.memberships.activate', $subscription) }}">@csrf <button class="text-sm text-lamu-800">Activate</button></form>
                        @endif
                        @if ($subscription->status === \App\Enums\SubscriptionStatus::Active)
                            <form method="POST" action="{{ route('staff.memberships.renew', $subscription) }}">@csrf <button class="text-sm text-lamu-800">Renew</button></form>
                            <form method="POST" action="{{ route('staff.memberships.cancel', $subscription) }}">@csrf <button class="text-sm text-lamu-800">Cancel</button></form>
                            <form method="POST" action="{{ route('staff.memberships.credit', $subscription) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf
                                <input type="number" name="days" min="1" max="90" required placeholder="Days" class="w-20 rounded border border-sand-200 px-2 py-1 text-sm">
                                <input name="reason" required placeholder="Reason" class="rounded border border-sand-200 px-2 py-1 text-sm">
                                <button class="text-sm text-lamu-800">Authorize credit</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No membership requests" message="Buyer and broker accounts can request a pass. A new investor user is not created." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
