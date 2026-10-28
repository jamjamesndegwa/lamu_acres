<x-workspace-layout :heading="$sourcingRequest->reference">
    <div class="mt-4 max-w-5xl rounded-2xl border border-lamu-800 bg-lamu-900 px-5 py-4 text-white shadow-md">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/80">Land search</p>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-white/90">
                    <x-status-badge :label="$sourcingRequest->status->label()" class="bg-white/20 text-white" />
                    <span>{{ $sourcingRequest->location_label ?: 'Map centre' }}</span>
                    @if ($sourcingRequest->radius_km)
                        <span class="text-white/70">· {{ $sourcingRequest->radius_km }} km radius</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('buyer.sourcing.index') }}" class="rounded-full border border-white/30 px-4 py-2 text-sm font-medium text-white hover:bg-white/10">All searches</a>
                @if ($sourcingRequest->status->value !== 'closed')
                    <a href="{{ route('buyer.sourcing.edit', $sourcingRequest) }}" data-turbo-frame="modal" class="rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900">Edit requirements</a>
                @endif
            </div>
        </div>
    </div>

    <section class="mt-6 max-w-5xl" aria-labelledby="search-progress">
        <h2 id="search-progress" class="sr-only">Search progress</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-kpi label="Status" :value="$sourcingRequest->status->label()" />
            <x-kpi label="Exact matches" :value="$exactMatches->count()" />
            <x-kpi label="Near matches" :value="$nearMatches->count()" />
        </div>
    </section>

    @if ($exactMatches->isEmpty() && $nearMatches->isEmpty())
        <div class="mt-6 max-w-5xl rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-stone-700">No published land fits this search yet. Staff will keep matching as new listings go live.</p>
            <p class="mt-3 text-sm">
                <a href="{{ route('land.index') }}" class="font-semibold text-lamu-800 hover:underline">Explore published land</a>
                @if ($sourcingRequest->status->value !== 'closed')
                    or
                    <a href="{{ route('buyer.sourcing.edit', $sourcingRequest) }}" data-turbo-frame="modal" class="font-semibold text-lamu-800 hover:underline">adjust your requirements</a>
                @endif
            </p>
        </div>
    @endif

    @foreach (['Exact matches' => $exactMatches, 'Near matches' => $nearMatches] as $heading => $rows)
        <section class="mt-8 max-w-5xl">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 class="home-serif text-xl font-semibold text-lamu-900">{{ $heading }}</h2>
                @if ($rows->isNotEmpty())
                    <p class="text-sm text-stone-600">{{ $rows->count() }} {{ $rows->count() === 1 ? 'property' : 'properties' }}</p>
                @endif
            </div>
            @if ($rows->isEmpty())
                <div class="mt-4">
                    <x-workspace.empty title="None in this group" message="Properties are grouped again whenever the search is saved." />
                </div>
            @else
                <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($rows as $match)
                        <x-sourcing-match-card :match="$match" />
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <div class="mt-10 max-w-5xl grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Your requirements</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-stone-500">Acreage</dt>
                    <dd class="mt-1 text-stone-800">{{ $sourcingRequest->min_acreage ?: 'Any' }} – {{ $sourcingRequest->max_acreage ?: 'Any' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-stone-500">Budget</dt>
                    <dd class="mt-1 text-stone-800">{{ $sourcingRequest->maximum_budget ? 'KSh '.number_format($sourcingRequest->maximum_budget) : 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-stone-500">Intended use</dt>
                    <dd class="mt-1 text-stone-800">{{ $sourcingRequest->intended_use?->label() ?: 'No preference' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-stone-500">Amenities</dt>
                    <dd class="mt-1 text-stone-800">{{ $sourcingRequest->amenities->map(fn ($amenity) => $amenity->amenity->label().($amenity->required ? ' (required)' : ' (preferred)'))->implode(', ') ?: 'None recorded' }}</dd>
                </div>
            </dl>
            @if ($sourcingRequest->notes)
                <p class="mt-4 border-t border-sand-100 pt-4 text-sm text-stone-700">{{ $sourcingRequest->notes }}</p>
            @endif
        </section>

        <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Search area</h2>
            <p class="mt-2 text-sm text-stone-600">Centre of your pin and radius used for distance matching.</p>
            <div class="mt-4 overflow-hidden rounded-xl border border-sand-200">
                <x-map-viewer :latitude="$sourcingRequest->centre_latitude" :longitude="$sourcingRequest->centre_longitude" empty="No map centre has been saved for this search." />
            </div>
        </section>
    </div>

    <section class="mt-10 max-w-5xl">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">History</h2>
        <div class="mt-4 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
            <x-activity-timeline :items="$sourcingRequest->activities->map(fn ($activity) => [
                'when' => $activity->created_at->toDayDateTimeString(),
                'text' => $activity->summary,
            ])->all()" />
        </div>
    </section>
</x-workspace-layout>
