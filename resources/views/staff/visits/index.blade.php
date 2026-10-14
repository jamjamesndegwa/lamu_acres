<x-workspace-layout heading="Site visits">
    <div class="mt-6 space-y-4">
        @forelse ($visits as $visit)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="font-medium text-lamu-900">{{ $visit->property->title }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $visit->buyer->name }} · {{ $visit->status->label() }} · {{ $visit->assignee->name ?? 'Unassigned' }}</p>
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
        @empty
            <x-workspace.empty title="No visits" message="Buyer visit requests will appear here." />
        @endforelse
    </div>
    <div class="mt-4"><x-workspace.pagination :paginator="$visits" /></div>
</x-workspace-layout>
