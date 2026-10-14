<x-workspace-layout :heading="$sourcingRequest->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $sourcingRequest->buyer->name }} · {{ $sourcingRequest->status->label() }} · {{ $sourcingRequest->location_label ?: 'Map centre' }}</p>

    <dl class="mt-6 grid max-w-3xl gap-3 rounded-2xl border border-sand-200 bg-white p-5 text-sm sm:grid-cols-2">
        <div><dt class="font-medium text-lamu-900">Radius</dt><dd>{{ $sourcingRequest->radius_km ? $sourcingRequest->radius_km.' km' : 'Not set' }}</dd></div>
        <div><dt class="font-medium text-lamu-900">Budget</dt><dd>{{ $sourcingRequest->maximum_budget ? 'KSh '.number_format($sourcingRequest->maximum_budget) : 'Not set' }}</dd></div>
        <div><dt class="font-medium text-lamu-900">Acreage</dt><dd>{{ $sourcingRequest->min_acreage ?: 'Any' }} – {{ $sourcingRequest->max_acreage ?: 'Any' }}</dd></div>
        <div><dt class="font-medium text-lamu-900">Intended use</dt><dd>{{ $sourcingRequest->intended_use?->label() ?: 'Not set' }}</dd></div>
        <div class="sm:col-span-2"><dt class="font-medium text-lamu-900">Notes</dt><dd class="whitespace-pre-line">{{ $sourcingRequest->notes ?: 'None' }}</dd></div>
        <div class="sm:col-span-2"><dt class="font-medium text-lamu-900">Map criteria</dt><dd class="break-all">{{ $sourcingRequest->centre_wkt ?: 'No centre' }} @if ($sourcingRequest->boundary_wkt) · polygon stored @endif</dd></div>
    </dl>

    @can('assign', $sourcingRequest)
        <form method="POST" action="{{ route('staff.sourcing.assign', $sourcingRequest) }}" data-turbo-frame="_top" data-confirm="Assign this search?" class="mt-6 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <div>
                <label for="assigned_to" class="block text-sm font-medium">Staff member</label>
                <select id="assigned_to" name="assigned_to" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected($sourcingRequest->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Assign</button>
        </form>
    @endcan

    @can('updateStatus', $sourcingRequest)
        <form method="POST" action="{{ route('staff.sourcing.update', $sourcingRequest) }}" data-turbo-frame="_top" data-confirm="Update this search status?" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <div>
                <label for="status" class="block text-sm font-medium">Status</label>
                <select id="status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($sourcingRequest->status === $status)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save status</button>
        </form>
    @endcan

    @foreach (['Exact matches' => $exactMatches, 'Near matches' => $nearMatches] as $heading => $rows)
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">{{ $heading }}</h2>
            <div class="mt-4 space-y-3">
                @forelse ($rows as $match)
                    <article class="rounded-2xl border border-sand-200 bg-white p-5">
                        <p class="font-medium text-lamu-900">{{ $match->property->title }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ implode(' ', $match->reasons ?? []) ?: 'Meets the recorded criteria.' }}</p>
                        @can('assign', $sourcingRequest)
                            <form method="POST" action="{{ route('staff.sourcing.approve', [$sourcingRequest, $match]) }}" data-turbo-frame="_top" data-confirm="Add this property to the buyer shortlist?" class="mt-3">
                                @csrf
                                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Approve for buyer</button>
                            </form>
                        @endcan
                    </article>
                @empty
                    <x-workspace.empty title="None in this group" message="Matching runs when the buyer saves the search." />
                @endforelse
            </div>
        </section>
    @endforeach

    <section class="mt-8">
        <h2 class="font-semibold text-lamu-900">History</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($sourcingRequest->activities as $activity)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">{{ $activity->summary }}</li>
            @endforeach
        </ol>
    </section>
</x-workspace-layout>
