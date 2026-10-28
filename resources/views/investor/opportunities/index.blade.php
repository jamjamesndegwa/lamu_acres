@php
    $currentQuery = \Illuminate\Support\Arr::except(
        array_filter(request()->query(), fn ($value) => $value !== null && $value !== ''),
        ['page'],
    );
    $viewUrl = function (string $view) use ($directoryUrl, $currentQuery): string {
        $query = \Illuminate\Support\Arr::except($currentQuery, ['view']);
        if ($view === 'list') {
            $query['view'] = 'list';
        }

        return $query === [] ? $directoryUrl : $directoryUrl.'?'.http_build_query($query);
    };
    $sortOptions = [
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'acres_asc' => 'Acreage: small to large',
        'acres_desc' => 'Acreage: large to small',
    ];
    $resultCount = $listings->total();
    $indexRoute = $indexRoute ?? 'land.index';
@endphp

<x-workspace-layout heading="Investment Opportunities">
    <p class="mt-2 max-w-3xl text-sm text-stone-700">Browse published land with location-based intelligence for your Investor Pass. Asking prices here are listing prices—not verified sale outcomes.</p>

    <div class="mt-6 grid w-full gap-4 lg:grid-cols-[230px_minmax(0,1fr)]">
        <div class="contents lg:sticky lg:top-[60px] lg:col-start-1 lg:block lg:max-h-[calc(100dvh-72px)] lg:space-y-3 lg:self-start lg:overflow-y-auto lg:[scrollbar-width:thin]">
            <div class="sticky top-[65px] z-20 -mx-4 flex items-center justify-between gap-3 border-b border-sand-200 bg-sand-50/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 lg:hidden">
                <button type="button" data-dialog-open="investor-opportunity-filters" aria-controls="investor-opportunity-filters" class="inline-flex h-10 items-center gap-2 rounded-md border border-lamu-800 bg-white px-4 text-sm font-medium text-lamu-900">
                    Filters
                    @if ($filterChips !== [])
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-lamu-800 px-1.5 text-xs text-white">{{ count($filterChips) }}</span>
                    @endif
                </button>
                <p class="text-sm text-stone-600">{{ $resultCount }} {{ \Illuminate\Support\Str::plural('listing', $resultCount) }}</p>
            </div>
            <dialog id="investor-opportunity-filters" class="public-mobile-menu lg:hidden" aria-labelledby="investor-opportunity-filters-title">
                <div class="flex items-center justify-between border-b border-sand-200 px-4 py-3">
                    <h2 id="investor-opportunity-filters-title" class="home-serif text-lg font-semibold text-lamu-900">Filters</h2>
                    <button type="button" data-dialog-close class="inline-flex size-10 items-center justify-center rounded-full hover:bg-lamu-100" aria-label="Close filters"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4">
                    @include('public.land.filters')
                    @include('public.land.locations', ['headingId' => 'investor-opportunity-locations-mobile', 'navClass' => 'mt-5 border-t border-sand-200 pt-4', 'touch' => true, 'indexRoute' => $indexRoute])
                </div>
            </dialog>
            <div class="hidden rounded-lg border border-sand-200 bg-white p-3 shadow-sm lg:block">
                @include('public.land.filters', ['showHeading' => true])
            </div>
            @include('public.land.locations', ['headingId' => 'investor-opportunity-locations', 'navClass' => 'hidden rounded-lg border border-sand-200 bg-white p-3 shadow-sm lg:block', 'indexRoute' => $indexRoute])
        </div>

        <section aria-labelledby="investor-opportunity-results" class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="investor-opportunity-results" class="home-serif text-lg font-semibold text-stone-900">{{ $resultCount }} published {{ \Illuminate\Support\Str::plural('opportunity', $resultCount) }}</h2>
                    @if ($filterChips !== [])
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($filterChips as $chip)
                                <li><a href="{{ $chip['url'] }}" class="inline-flex items-center rounded-md bg-lamu-100 px-2.5 py-1 text-xs text-lamu-900">{{ $chip['label'] }} ×</a></li>
                            @endforeach
                            <li><a href="{{ $clearFiltersUrl }}" class="text-xs font-medium text-lamu-800 underline">Clear all</a></li>
                        </ul>
                    @endif
                </div>
                <form method="GET" action="{{ $directoryUrl }}" class="flex items-center gap-2">
                    @foreach (\Illuminate\Support\Arr::except($currentQuery, ['sort']) as $name => $value)
                        @if (is_string($value))
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="investor-opportunity-sort" class="text-xs text-stone-600">Sort</label>
                    <select id="investor-opportunity-sort" name="sort" data-auto-submit class="h-8 rounded-md border border-sand-200 bg-white px-2 text-xs">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if ($listings->isEmpty())
                <div class="mt-4 rounded-lg border border-sand-200 bg-white p-5 text-sm text-stone-600">
                    <p class="font-medium text-stone-900">No published land matches these filters.</p>
                    <p class="mt-1">Try widening price or acreage, or <a href="{{ $clearFiltersUrl }}" class="font-medium text-lamu-800 underline">clear filters</a>.</p>
                </div>
            @else
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($listings as $listing)
                        <x-land-preview-card :listing="$listing" />
                    @endforeach
                </div>
            @endif

            @if ($listings->hasPages())
                <div class="mt-4">{{ $listings->links() }}</div>
            @endif
        </section>
    </div>
</x-workspace-layout>
