<article id="visit-{{ $visit->id }}" class="rounded-2xl border border-sand-200 bg-white p-5">
    <p class="font-medium text-lamu-900">{{ $visit->property->title }}</p>
    <p class="mt-1 text-sm text-stone-600">
        {{ $visit->buyer->name }} · {{ $visit->status->label() }} · {{ $visit->assignee->name ?? 'Unassigned' }}
    </p>
    <p class="mt-1 text-sm text-stone-600">
        Requested {{ $visit->requested_for?->toDayDateTimeString() ?: 'with no time' }}
        @if ($visit->confirmed_for)
            · Confirmed {{ $visit->confirmed_for->toDayDateTimeString() }}
        @endif
    </p>
    @if ($visit->notes)
        <p class="mt-2 text-sm text-stone-700">{{ $visit->notes }}</p>
    @endif
    @if ($visit->reschedule_requested_at)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="font-medium text-amber-900">Buyer requested a reschedule</p>
            <p class="mt-1 text-sm text-stone-700">Preferred: {{ $visit->reschedule_preferred_for->toDayDateTimeString() }}</p>
            @if ($visit->reschedule_note)
                <p class="mt-1 whitespace-pre-line text-sm text-stone-700">{{ $visit->reschedule_note }}</p>
            @endif
            @can('manage', $visit)
                <form method="POST" action="{{ route('staff.visits.reschedule.resolve', $visit) }}" data-turbo-frame="_top" data-confirm="Resolve this reschedule request and notify the buyer?" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium" for="decision-{{ $visit->id }}">Decision</label>
                        <select id="decision-{{ $visit->id }}" name="decision" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                            <option value="accept">Accept and confirm new time</option>
                            <option value="decline">Decline and keep current time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="reschedule-time-{{ $visit->id }}">Confirmed date and time (required if accepted)</label>
                        <input id="reschedule-time-{{ $visit->id }}" type="datetime-local" name="confirmed_for" min="{{ now()->format('Y-m-d\\TH:i') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium" for="reschedule-response-{{ $visit->id }}">Reply to buyer</label>
                        <textarea id="reschedule-response-{{ $visit->id }}" name="response" maxlength="1000" required rows="2" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
                    </div>
                    <button class="w-fit rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send decision</button>
                </form>
            @endcan
        </div>
    @endif
    @can('assign', $visit)
        <form method="POST" action="{{ route('staff.visits.assign', $visit) }}" data-turbo-frame="_top" data-confirm="Assign this visit?" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium" for="agent-{{ $visit->id }}">Agent</label>
                <select id="agent-{{ $visit->id }}" name="assigned_to" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected($visit->assigned_to === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Assign agent</button>
        </form>
    @endcan
    @can('manage', $visit)
        @if ($visit->status->transitions() !== [])
            <form method="POST" action="{{ route('staff.visits.update', $visit) }}" data-turbo-frame="_top" data-confirm="Update this visit?" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <select name="status" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @foreach ($visit->status->transitions() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                @if (collect($visit->status->transitions())->contains(fn ($status) => $status->value === 'confirmed'))
                    <input type="datetime-local" name="confirmed_for" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Confirmed time">
                @endif
                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Update visit</button>
            </form>
        @endif
    @endcan
</article>
