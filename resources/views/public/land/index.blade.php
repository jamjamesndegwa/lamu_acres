@php
    $directoryUrl = route('land.index', array_filter([
        'county' => $county?->slug,
        'locality' => $locality?->slug,
    ]));
    $heading = $locality
        ? 'Land in '.$locality->name
        : ($county ? 'Land in '.$county->name : 'Land in Lamu');
@endphp

<x-public-layout
    :title="$heading.' — Lamu Acres'"
    description="Published land listings in Lamu. Filter by location, price, price per acre, acreage, sale or lease, and property type."
>
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7rem)] lg:self-start lg:overflow-y-auto">
            <details class="rounded-2xl border border-sand-200 bg-white p-4 lg:hidden">
                <summary class="cursor-pointer font-medium text-lamu-900">Filters</summary>
                <div class="mt-4">
                    @include('public.land.filters', ['directoryUrl' => $directoryUrl])
                </div>
            </details>
            <div class="hidden rounded-2xl border border-sand-200 bg-white p-4 lg:block">
                <h2 class="font-semibold text-lamu-900">Filters</h2>
                <div class="mt-4">
                    @include('public.land.filters', ['directoryUrl' => $directoryUrl])
                </div>
            </div>

            <nav class="mt-4 rounded-2xl border border-sand-200 bg-white p-4" aria-label="Locations">
                <h2 class="font-semibold text-lamu-900">Locations</h2>
                <a class="mt-3 block text-sm text-lamu-800 hover:underline" href="{{ route('land.index') }}">All published land</a>
                @forelse ($counties as $place)
                    <div class="mt-3">
                        <a @class([
                            'block text-sm hover:underline',
                            'font-semibold text-lamu-900' => $county?->is($place),
                            'text-lamu-800' => ! $county?->is($place),
                        ]) href="{{ route('land.index', $place) }}">{{ $place->name }} ({{ $place->available_listings_count }})</a>
                        @if ($place->localities->isNotEmpty())
                            <ul class="mt-1 space-y-1 border-l border-sand-200 pl-3 text-sm">
                                @foreach ($place->localities as $area)
                                    <li>
                                        <a @class([
                                            'hover:underline',
                                            'font-semibold text-lamu-900' => $locality?->is($area),
                                            'text-lamu-800' => ! $locality?->is($area),
                                        ]) href="{{ route('land.index', [$place, $area]) }}">{{ $area->name }} ({{ $area->available_listings_count }})</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-stone-600">No locations are recorded yet.</p>
                @endforelse
            </nav>
        </aside>

        <section>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-semibold text-lamu-900">{{ $heading }}</h1>
                    <p class="mt-2 text-sm text-stone-600">{{ number_format($listings->total()) }} available {{ \Illuminate\Support\Str::plural('listing', $listings->total()) }}</p>
                </div>
                <div class="flex gap-2 text-sm">
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'cards']) }}" @class(['rounded-full px-3 py-1', 'bg-lamu-900 text-white' => $filters['view'] === 'cards', 'border border-sand-200' => $filters['view'] !== 'cards'])>Cards</a>
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" @class(['rounded-full px-3 py-1', 'bg-lamu-900 text-white' => $filters['view'] === 'list', 'border border-sand-200' => $filters['view'] !== 'list'])>List</a>
                </div>
            </div>

            @if ($listings->isEmpty())
                <p class="mt-8 rounded-2xl border border-sand-200 bg-white p-6 text-stone-600">
                    @php
                        $hasFilters = collect(request()->query())->except(['page', 'view', 'sort'])->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
                    @endphp
                    @if ($hasFilters)
                        No land has been published for this search.
                    @elseif ($locality)
                        No land has been published in {{ $locality->name }} yet.
                    @elseif ($county)
                        No land has been published in {{ $county->name }} yet.
                    @else
                        No land has been published yet.
                    @endif
                    @auth
                        @if (auth()->user()->hasPermission('workspace.buyer'))
                            <a href="{{ route('buyer.sourcing.create') }}" class="mt-4 inline-block font-medium text-lamu-800 hover:underline">Find land for me</a>
                        @endif
                    @endauth
                </p>
            @elseif ($filters['view'] === 'list')
                <ul class="mt-6 divide-y divide-sand-200 overflow-hidden rounded-2xl border border-sand-200 bg-white">
                    @foreach ($listings as $listing)
                        <li class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <a href="{{ $listing->publicUrl() }}" class="font-medium text-lamu-900 hover:underline">{{ $listing->title }}</a>
                                <p class="text-sm text-stone-600">{{ $listing->locationLabel() }} · {{ $listing->purpose?->label() }}</p>
                            </div>
                            <p class="text-sm font-medium text-lamu-900">{{ $listing->formattedPrice() ?? 'Price on request' }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($listings as $listing)
                        <x-listing-card :listing="$listing" />
                    @endforeach
                </div>
            @endif

            <div class="mt-8">
                {{ $listings->links() }}
            </div>
        </section>
    </div>
</x-public-layout>
