<x-workspace-layout heading="Site Visits">
    <p class="mt-3 max-w-3xl text-stone-700">Track visit requests from property listings and approved matches. Appointment updates appear here and in your in-app notifications. We attempt email confirmations when email updates are enabled and mail delivery is configured. SMS requires SMS updates to be enabled and an active Lamu Acres SMS provider.</p>

    @if ($visits->isNotEmpty())
        <x-buyer.next-step
            label="Review your visit requests"
            :href="route('buyer.visits.index')"
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
                        @if ($visit->status === \App\Enums\SiteVisitStatus::Confirmed)
                            @if ($visit->reschedule_requested_at)
                                <p class="mb-2 text-left text-xs text-amber-800">Reschedule requested for {{ $visit->reschedule_preferred_for->toDayDateTimeString() }}. Your current appointment remains confirmed until staff responds.</p>
                            @elseif ($visit->buyer_confirmed_at)
                                <p class="mb-2 text-left text-xs text-green-800">You confirmed these appointment details.</p>
                            @endif
                            @can('confirmDetails', $visit)
                                @if (! $visit->reschedule_requested_at)
                                    <form method="POST" action="{{ route('buyer.visits.confirm-details', $visit) }}" data-turbo-frame="_top" data-confirm="Confirm that you can attend this appointment at the time shown?" class="mb-2">
                                        @csrf
                                        <button class="font-medium text-lamu-800">Confirm appointment details</button>
                                    </form>
                                @endif
                            @endcan
                            @can('requestReschedule', $visit)
                                <details class="mb-2 text-left">
                                    <summary class="cursor-pointer font-medium text-lamu-800">Request a different time</summary>
                                    <form method="POST" action="{{ route('buyer.visits.reschedule', $visit) }}" data-turbo-frame="_top" data-confirm="Send this reschedule request? Your current appointment stays confirmed until staff responds." class="mt-2 space-y-2">
                                        @csrf
                                        <label class="block text-xs font-medium" for="preferred-for-{{ $visit->id }}">Preferred new date and time</label>
                                        <input id="preferred-for-{{ $visit->id }}" type="datetime-local" name="preferred_for" min="{{ now()->format('Y-m-d\\TH:i') }}" required class="w-full rounded-lg border border-sand-200 px-2 py-2">
                                        <textarea name="note" rows="2" maxlength="1000" placeholder="Optional note" class="w-full rounded-lg border border-sand-200 px-2 py-2"></textarea>
                                        <button class="rounded-full border border-lamu-800 px-3 py-2 font-medium text-lamu-800">Send reschedule request</button>
                                    </form>
                                </details>
                            @endcan
                        @endif
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
                    <td colspan="5" class="px-4 py-8"><x-workspace.empty title="No visits" message="Request a visit from a property listing or an approved match." action="Find land" :href="route('find-land.index')" /></td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$visits" /></div>
    </div>
</x-workspace-layout>
