<x-workspace-layout :heading="$match->property->title">
    <p class="mt-3 flex flex-wrap items-center gap-3 text-sm text-stone-600">
        <span>{{ $match->status->label() }} · {{ $match->property->locationLabel() }}</span>
        @if ($match->property->isPubliclyListed())
            <a href="{{ $match->property->publicUrl() }}" class="font-medium text-lamu-800 hover:underline">View property</a>
        @endif
    </p>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">How this land compares</h2>
        @if ($match->sourcingRequest)
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-stone-500">Search acreage</dt>
                    <dd>{{ $match->sourcingRequest->min_acreage ?: 'Any' }} – {{ $match->sourcingRequest->max_acreage ?: 'Any' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">This property</dt>
                    <dd>{{ $match->property->acreage }} acres · {{ $match->property->land_use?->label() ?: 'Use not recorded' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Search budget</dt>
                    <dd>{{ $match->sourcingRequest->maximum_budget ? 'KSh '.number_format($match->sourcingRequest->maximum_budget) : 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Asking price</dt>
                    <dd>
                        {{ $match->property->price_amount ? 'KSh '.number_format($match->property->price_amount) : 'Not published' }}
                        @if ($match->property->pricePerAcre())
                            · KSh {{ number_format($match->property->pricePerAcre()) }} per acre
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-stone-500">Distance</dt>
                    <dd>{{ $fit?->distance_km !== null ? $fit->distance_km.' km from the search centre' : 'Distance not recorded' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Verification</dt>
                    <dd>{{ $match->property->verification_level?->label() ?? 'Not reviewed' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Intended use</dt>
                    <dd>{{ $match->sourcingRequest->intended_use?->label() ?: 'No preference' }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Suitability</dt>
                    <dd>{{ $fit?->band?->label() ?? 'Approved' }}{{ $fit && filled($fit->reasons) ? ' · '.implode(' ', $fit->reasons) : '' }}</dd>
                </div>
            </dl>
        @else
            <p class="mt-3 text-sm text-stone-700">This match was approved without a recorded search. You can still request a visit, send an offer, or ask for documents.</p>
        @endif
        @if ($match->sourcingRequest && $match->sourcingRequest->amenities->isNotEmpty())
            <h3 class="mt-5 text-sm font-semibold text-lamu-900">Requirements</h3>
            <ul class="mt-2 space-y-1 text-sm text-stone-700">
                @foreach ($match->sourcingRequest->amenities as $wanted)
                    @php($state = $match->property->amenityStates->first(fn ($row) => $row->amenity === $wanted->amenity))
                    <li>{{ $wanted->amenity->label() }} · {{ $wanted->required ? 'Required' : 'Preferred' }} · {{ $state?->state?->label() ?? 'Unknown' }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Directions</h2>
        @if ($directions && $directions['latitude'] !== null)
            <p class="mt-3 text-sm text-stone-700">Exact location: {{ $directions['latitude'] }}, {{ $directions['longitude'] }}</p>
            <x-map-viewer :latitude="$directions['latitude']" :longitude="$directions['longitude']" />
        @else
            <p class="mt-3 text-sm text-stone-700">Exact directions stay hidden until a visit is confirmed.</p>
        @endif
    </section>

    @unless ($match->status->value === 'shortlisted')
        <x-buyer.next-step
            label="Explore more land"
            :href="route('land.index')"
            detail="This match is {{ strtolower($match->status->label()) }}. Review any visits or offers below, or keep looking."
        />
    @endunless

    @if ($match->status->value === 'shortlisted')
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('buyer.matches.show', [$match, 'dialog' => 'visit']) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Request a site visit</a>
            <a href="{{ route('buyer.matches.show', [$match, 'dialog' => 'offer']) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Make an offer</a>
            <a href="{{ route('buyer.matches.show', [$match, 'dialog' => 'documents']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Request documents</a>
            <a href="{{ route('buyer.matches.show', [$match, 'dialog' => 'reject']) }}" data-turbo-frame="modal" class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium">Remove from shortlist</a>
        </div>
    @endif

    @if ($match->buyer_feedback)
        <p class="mt-6 max-w-3xl text-sm text-stone-700">Your feedback: {{ $match->buyer_feedback }}</p>
    @endif

    <section class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Visits</h2>
        <div class="mt-4 space-y-3">
            @forelse ($match->visits as $visit)
                <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p>{{ $visit->status->label() }} · requested for {{ $visit->requested_for->toDayDateTimeString() }}</p>
                    @if ($visit->buyer_acknowledged_at)
                        <p class="mt-2 text-stone-600">Viewing acknowledged {{ $visit->buyer_acknowledged_at->toDayDateTimeString() }}</p>
                    @endif
                    @can('cancel', $visit)
                        <form method="POST" action="{{ route('buyer.visits.cancel', $visit) }}" data-turbo-frame="_top" data-confirm="Cancel this visit?" class="mt-3">
                            @csrf
                            <button class="text-sm font-medium text-lamu-700">Cancel visit</button>
                        </form>
                    @endcan
                    @can('acknowledge', $visit)
                        <form method="POST" action="{{ route('buyer.visits.acknowledge', $visit) }}" data-turbo-frame="_top" data-confirm="Confirm that you attended this viewing?" class="mt-3">
                            @csrf
                            <button class="text-sm font-medium text-lamu-700">Acknowledge viewing</button>
                        </form>
                    @endcan
                </article>
            @empty
                <p class="text-sm text-stone-600">No visit has been requested.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Offers</h2>
        <div class="mt-4 space-y-3">
            @forelse ($match->offers as $offer)
                <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p>KSh {{ number_format($offer->amount) }} · {{ $offer->status->label() }}</p>
                    @can('withdraw', $offer)
                        <form method="POST" action="{{ route('buyer.offers.withdraw', $offer) }}" data-turbo-frame="_top" data-confirm="Withdraw this offer?" class="mt-3">
                            @csrf
                            <button class="text-sm font-medium text-lamu-700">Withdraw offer</button>
                        </form>
                    @endcan
                </article>
            @empty
                <p class="text-sm text-stone-600">No offer has been submitted.</p>
            @endforelse
        </div>
    </section>

    <section class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Documents</h2>
        <ul class="mt-4 space-y-2 text-sm">
            @forelse ($match->documentRequests as $request)
                <li>{{ $request->status->label() }}</li>
            @empty
                <li class="text-stone-600">No document request has been sent.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Timeline</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($match->timeline as $entry)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p>{{ $entry->summary }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $entry->occurred_at->toDayDateTimeString() }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-workspace-layout>
