<x-workspace-layout heading="Referrals">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-stone-700">Buyer introductions you have registered. Commission protection starts only after staff accept the referral.</p>
        <a href="{{ route('broker.referrals.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Register buyer</a>
    </div>
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Buyer', 'Status', 'Properties']">
            @forelse ($referrals as $referral)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('broker.referrals.show', $referral) }}" class="font-medium text-lamu-900 hover:underline">{{ $referral->reference }}</a>
                    </td>
                    <td class="px-4 py-4">{{ $referral->buyer_name }}</td>
                    <td class="px-4 py-4">{{ $referral->status->label() }}</td>
                    <td class="px-4 py-4 text-stone-600">{{ $referral->properties->pluck('title')->join(', ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8">
                        <x-workspace.empty title="No referrals yet" message="Register a buyer against a published property when you have their consent." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$referrals" /></div>
    </div>
</x-workspace-layout>
