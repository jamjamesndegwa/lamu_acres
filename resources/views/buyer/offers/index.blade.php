<x-workspace-layout heading="My Offers">
    <p class="mt-3 max-w-3xl text-stone-700">Offers you have sent. An accepted offer does not mark the land as sold.</p>

    @if ($offers->isNotEmpty())
        <x-buyer.next-step
            label="Track this offer"
            :href="$offers->first()->property_match_id ? route('buyer.matches.show', $offers->first()->property_match_id) : route('buyer.matches.index')"
            detail="See the amount and status. Withdraw an offer that is still open, or keep browsing while the desk replies."
        />
    @endif
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Amount', 'Notes', 'Status', '']">
            @forelse ($offers as $offer)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">
                        @if ($offer->property_match_id)
                            <a href="{{ route('buyer.matches.show', $offer->property_match_id) }}" class="font-medium text-lamu-900 hover:underline">{{ $offer->property->title }}</a>
                        @else
                            {{ $offer->property->title }}
                        @endif
                        <p class="text-xs text-stone-500">{{ $offer->property->reference }}</p>
                    </td>
                    <td class="px-4 py-4 text-sm">KSh {{ number_format($offer->amount) }}</td>
                    <td class="px-4 py-4 text-sm text-stone-700">{{ $offer->message ? \Illuminate\Support\Str::limit($offer->message, 80) : '—' }}</td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$offer->status->label()" /></td>
                    <td class="px-4 py-4 text-right text-sm">
                        @can('withdraw', $offer)
                            <form method="POST" action="{{ route('buyer.offers.withdraw', $offer) }}" data-turbo-frame="_top" data-confirm="Withdraw this offer?">
                                @csrf
                                <button class="font-medium text-lamu-800">Withdraw</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8"><x-workspace.empty title="No offers" message="Submit an offer from an approved match." action="View matches" :href="route('buyer.matches.index')" /></td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$offers" /></div>
    </div>
</x-workspace-layout>
