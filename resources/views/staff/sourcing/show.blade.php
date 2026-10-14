<x-workspace-layout :heading="$sourcingRequest->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $sourcingRequest->buyer->name }} · {{ $sourcingRequest->status->label() }} · {{ $sourcingRequest->location_label ?: 'Map centre' }}</p>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Search radius</h2>
        <p class="mt-2 text-sm text-stone-600">
            {{ $sourcingRequest->radius_km ? 'Within '.$sourcingRequest->radius_km.' km of the recorded centre.' : 'No radius was recorded.' }}
            @if ($sourcingRequest->boundary_points)
                A drawn boundary is stored with this search.
            @endif
        </p>
        @if ($mapEmbed)
            <iframe title="Search radius map" class="mt-4 h-72 w-full rounded-2xl border border-sand-200" src="{{ $mapEmbed }}" loading="lazy"></iframe>
            <p class="mt-2 text-sm text-stone-600">The marker is the search centre. The frame covers the recorded radius. It is not a parcel boundary.</p>
        @else
            <p class="mt-3 text-sm text-stone-600">No map centre was recorded.</p>
        @endif
    </section>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Mandatory criteria</h2>
        <dl class="mt-3 grid gap-2 text-sm text-stone-700 sm:grid-cols-2">
            <div><dt class="font-medium text-lamu-900">Budget ceiling</dt><dd>{{ $sourcingRequest->maximum_budget ? 'KSh '.number_format($sourcingRequest->maximum_budget) : 'Not set' }}</dd></div>
            <div><dt class="font-medium text-lamu-900">Acreage</dt><dd>{{ $sourcingRequest->min_acreage ?: 'Any' }} – {{ $sourcingRequest->max_acreage ?: 'Any' }}</dd></div>
            <div><dt class="font-medium text-lamu-900">Intended use</dt><dd>{{ $sourcingRequest->intended_use?->label() ?: 'Not set' }}</dd></div>
            <div><dt class="font-medium text-lamu-900">Radius</dt><dd>{{ $sourcingRequest->radius_km ? $sourcingRequest->radius_km.' km' : 'Not set' }}</dd></div>
        </dl>
        <h3 class="mt-4 text-sm font-medium text-lamu-900">Required amenities</h3>
        <ul class="mt-2 space-y-1 text-sm text-stone-700">
            @forelse ($sourcingRequest->amenities->where('required', true) as $amenity)
                <li>{{ $amenity->amenity->label() }}</li>
            @empty
                <li>No amenity was marked required.</li>
            @endforelse
        </ul>
        @if ($sourcingRequest->amenities->where('required', false)->isNotEmpty())
            <p class="mt-3 text-sm text-stone-600">Also requested: {{ $sourcingRequest->amenities->where('required', false)->map(fn ($amenity) => $amenity->amenity->label())->join(', ') }}.</p>
        @endif
        @if ($sourcingRequest->notes)
            <p class="mt-3 whitespace-pre-line text-sm text-stone-700">{{ $sourcingRequest->notes }}</p>
        @endif
    </section>

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
                        <p class="mt-1 text-sm text-stone-600">
                            {{ $match->distance_km !== null ? $match->distance_km.' km from the centre. ' : '' }}{{ implode(' ', $match->reasons ?? []) ?: 'Meets the recorded criteria.' }}
                        </p>
                        @can('assign', $sourcingRequest)
                            <div class="mt-3 flex flex-wrap gap-3">
                                <form method="POST" action="{{ route('staff.sourcing.approve', [$sourcingRequest, $match]) }}" data-turbo-frame="_top" data-confirm="Add this property to the buyer shortlist?">
                                    @csrf
                                    @include('admin.approvals.return-field')
                                    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Approve for buyer</button>
                                </form>
                                <form method="POST" action="{{ route('staff.sourcing.reject', [$sourcingRequest, $match]) }}" data-turbo-frame="_top" data-confirm="Keep this property off the buyer shortlist?">
                                    @csrf
                                    @include('admin.approvals.return-field')
                                    <button class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium text-lamu-900">Reject match</button>
                                </form>
                            </div>
                        @endcan
                    </article>
                @empty
                    <x-workspace.empty title="None in this group" message="Matching runs when the buyer saves the search." />
                @endforelse
            </div>
        </section>
    @endforeach

    @if ($declinedMatches->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">Rejected matches</h2>
            <div class="mt-4 space-y-3">
                @foreach ($declinedMatches as $match)
                    <article class="rounded-2xl border border-sand-200 bg-white p-5">
                        <p class="font-medium text-lamu-900">{{ $match->property->title }}</p>
                        <p class="mt-1 text-sm text-stone-600">Kept off the shortlist.</p>
                        @can('assign', $sourcingRequest)
                            <form method="POST" action="{{ route('staff.sourcing.approve', [$sourcingRequest, $match]) }}" data-turbo-frame="_top" data-confirm="Add this property to the buyer shortlist?" class="mt-3">
                                @csrf
                                @include('admin.approvals.return-field')
                                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Approve for buyer</button>
                            </form>
                        @endcan
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8">
        <h2 class="font-semibold text-lamu-900">History</h2>
        <x-activity-timeline :items="$sourcingRequest->activities->map(fn ($activity) => ['text' => $activity->summary])->all()" />
    </section>
</x-workspace-layout>
