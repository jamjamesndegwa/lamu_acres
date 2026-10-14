<x-workspace-layout :heading="$sourcingRequest->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $sourcingRequest->status->label() }} · {{ $sourcingRequest->location_label ?: 'Map centre' }}</p>
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-stone-500">Acreage</dt><dd>{{ $sourcingRequest->min_acreage ?: 'Any' }} – {{ $sourcingRequest->max_acreage ?: 'Any' }}</dd></div>
        <div><dt class="text-stone-500">Budget</dt><dd>{{ $sourcingRequest->maximum_budget ? 'KSh '.number_format($sourcingRequest->maximum_budget) : 'Not set' }}</dd></div>
        <div><dt class="text-stone-500">Intended use</dt><dd>{{ $sourcingRequest->intended_use?->label() ?: 'No preference' }}</dd></div>
        <div><dt class="text-stone-500">Amenities</dt><dd>{{ $sourcingRequest->amenities->map(fn ($amenity) => $amenity->amenity->label().($amenity->required ? ' (required)' : ' (preferred)'))->implode(', ') ?: 'None recorded' }}</dd></div>
    </dl>
    @if ($sourcingRequest->notes)
        <p class="mt-3 max-w-3xl text-sm text-stone-700">{{ $sourcingRequest->notes }}</p>
    @endif
    @if ($sourcingRequest->status->value !== 'closed')
        <a href="{{ route('buyer.sourcing.edit', $sourcingRequest) }}" class="mt-4 inline-block text-sm font-medium text-lamu-700">Edit requirements</a>
    @endif
    @if ($exactMatches->isEmpty() && $nearMatches->isEmpty())
        <p class="mt-4 max-w-3xl text-sm text-stone-700">No published land fits this search yet. <a href="{{ route('land.index') }}" class="font-medium text-lamu-800 hover:underline">Explore land</a> while the desk continues matching, or adjust the requirements.</p>
    @endif

    <section class="mt-6" aria-labelledby="search-progress">
        <h2 id="search-progress" class="font-semibold text-lamu-900">Progress</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Status</dt>
                <dd class="mt-2 font-semibold text-lamu-900">{{ $sourcingRequest->status->label() }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Exact matches</dt>
                <dd class="mt-2 text-2xl font-semibold text-lamu-900">{{ $exactMatches->count() }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Near matches</dt>
                <dd class="mt-2 text-2xl font-semibold text-lamu-900">{{ $nearMatches->count() }}</dd>
            </div>
        </dl>
    </section>

    @foreach (['Exact matches' => $exactMatches, 'Near matches' => $nearMatches] as $heading => $rows)
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">{{ $heading }}</h2>
            @if ($rows->isEmpty())
                <div class="mt-4">
                    <x-workspace.empty title="None in this group" message="Properties are grouped again whenever the search is saved." />
                </div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($rows as $match)
                        <article class="flex h-full flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white">
                            <div class="border-b border-sand-200 bg-lamu-100 px-5 py-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $match->band->label() }} @if ($match->distance_km !== null) · {{ $match->distance_km }} km @endif</p>
                                <h3 class="mt-1 text-lg font-semibold text-lamu-900">
                                    <a href="{{ $match->property->publicUrl() }}" class="hover:underline">{{ $match->property->title }}</a>
                                </h3>
                            </div>
                            <div class="flex flex-1 flex-col gap-2 px-5 py-4 text-sm text-stone-700">
                                <p>{{ $match->property->locationLabel() }}</p>
                                <p>{{ implode(' ', $match->reasons ?? []) ?: 'Meets the recorded criteria.' }}</p>
                                <a href="{{ $match->property->publicUrl() }}" class="mt-auto pt-3 text-sm font-medium text-lamu-800 hover:underline">Open property</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <section class="mt-8">
        <h2 class="font-semibold text-lamu-900">History</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($sourcingRequest->activities as $activity)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p>{{ $activity->summary }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $activity->created_at->toDayDateTimeString() }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-workspace-layout>
