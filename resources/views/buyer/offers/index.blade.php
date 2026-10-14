<x-workspace-layout heading="My Offers">
    <p class="mt-3 max-w-3xl text-stone-700">Offers you have sent. An accepted offer does not mark the land as sold.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Amount', 'Status', '']">
            @forelse ($offers as $offer)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">{{ $offer->property->title }}</td>
                    <td class="px-4 py-4 text-sm">KSh {{ number_format($offer->amount) }}</td>
                    <td class="px-4 py-4 text-sm">{{ $offer->status->label() }}</td>
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
                    <td colspan="4" class="px-4 py-8"><x-workspace.empty title="No offers" message="Submit an offer from an approved match." action="View matches" :href="route('buyer.matches.index')" /></td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$offers" /></div>
    </div>
</x-workspace-layout>
