<x-workspace-layout heading="Buyer referrals">
    <p class="mt-3 max-w-3xl text-stone-700">Review introductions before any commission protection applies. Overlaps stay open until you decide them.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Broker', 'Buyer', 'Status']">
            @forelse ($referrals as $referral)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a href="{{ route('staff.referrals.show', $referral) }}" class="font-medium text-lamu-900 hover:underline">{{ $referral->reference }}</a></td>
                    <td class="px-4 py-4">{{ $referral->broker?->name }}</td>
                    <td class="px-4 py-4">{{ $referral->buyer_name }}</td>
                    <td class="px-4 py-4"><x-status-badge :label="$referral->status->label().($referral->conflicts ? ' · conflict' : '')" /></td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No referrals" message="Broker introductions will be listed here." /></td></tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$referrals" /></div>
    </div>
</x-workspace-layout>
