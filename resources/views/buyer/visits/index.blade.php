<x-workspace-layout heading="Site Visits">
    <p class="mt-3 max-w-3xl text-stone-700">Visits you have requested on approved matches.</p>

    @if ($visits->isNotEmpty())
        <x-buyer.next-step
            label="Review this visit"
            :href="$visits->first()->property_match_id ? route('buyer.matches.show', $visits->first()->property_match_id) : route('buyer.matches.index')"
            detail="Cancel a visit that has not happened, acknowledge one you attended, then submit an offer if you want to proceed."
        />
    @endif
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Status', 'When', 'Representative', '']">
            @forelse ($visits as $visit)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">
                        @if ($visit->property_match_id)
                            <a href="{{ route('buyer.matches.show', $visit->property_match_id) }}" class="font-medium text-lamu-900 hover:underline">{{ $visit->property->title }}</a>
                        @else
                            <span class="font-medium text-lamu-900">{{ $visit->property->title }}</span>
                        @endif
                        @if ($visit->notes)
                            <p class="mt-1 text-stone-600">{{ \Illuminate\Support\Str::limit($visit->notes, 120) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$visit->status->label()" /></td>
                    <td class="px-4 py-4 text-sm">
                        {{ ($visit->confirmed_for ?? $visit->requested_for)->toDayDateTimeString() }}
                        @if ($visit->confirmed_for)
                            <p class="text-xs text-stone-500">Confirmed</p>
                        @else
                            <p class="text-xs text-stone-500">Requested</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ $visit->assignee->name ?? 'Not assigned yet' }}</td>
                    <td class="px-4 py-4 text-right text-sm">
                        @can('cancel', $visit)
                            <form method="POST" action="{{ route('buyer.visits.cancel', $visit) }}" data-turbo-frame="_top" data-confirm="Cancel this visit?">
                                @csrf
                                <button class="font-medium text-lamu-800">Cancel</button>
                            </form>
                        @endcan
                        @can('acknowledge', $visit)
                            <form method="POST" action="{{ route('buyer.visits.acknowledge', $visit) }}" data-turbo-frame="_top" data-confirm="Confirm that you attended this viewing?" class="mt-2">
                                @csrf
                                <button class="font-medium text-lamu-800">Acknowledge viewing</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8"><x-workspace.empty title="No visits" message="Request a visit from an approved match." action="View matches" :href="route('buyer.matches.index')" /></td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$visits" /></div>
    </div>
</x-workspace-layout>
