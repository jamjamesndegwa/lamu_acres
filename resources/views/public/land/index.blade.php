@php
    $directoryUrl = route('land.index', array_filter([
        'county' => $county?->slug,
        'locality' => $locality?->slug,
    ]));
    $areaName = $locality?->name ?? $county?->name;
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
    $findLandModal = auth()->check() && auth()->user()->hasPermission('workspace.buyer');
    $findLandHref = $findLandModal ? route('buyer.sourcing.create') : route('home').'#find-land';
    $mapImage = collect(['images/lamu_area_overview.jpg', 'images/lamu_map.jpg', 'images/lamu_map.png'])
        ->first(fn (string $path) => is_file(public_path($path)));
    $resultCount = $previewListings->total();
    $breadcrumbs = [['label' => 'Home', 'href' => route('home')]];
    $breadcrumbs[] = $areaName
        ? ['label' => 'Explore Land', 'href' => route('land.index')]
        : ['label' => 'Explore Land', 'current' => true];
    if ($areaName) {
        $breadcrumbs[] = ['label' => $areaName, 'current' => true];
    }
@endphp

<x-public-layout
    :title="($areaName ? 'Land in '.$areaName : 'Explore Land in Lamu').' — Lamu Acres'"
    description="Explore land in Lamu. Filter coastal plots and acreage by location, purpose, property type, price, acreage and price per acre."
>
    <section class="relative isolate overflow-hidden bg-lamu-900">
        <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-[70%_50%]" fetchpriority="high">
        <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto flex min-h-[120px] w-full max-w-[1440px] flex-col justify-center px-4 py-4 sm:px-6 lg:min-h-[112px] lg:w-[90%] lg:px-0 lg:py-3">
            <div class="[&_nav]:text-xs">@include('account.applications.partials.breadcrumb', ['items' => $breadcrumbs, 'variant' => 'dark'])</div>
            <h1 class="home-serif mt-1 max-w-[30rem] text-2xl font-semibold leading-tight text-white [text-shadow:0_1px_2px_rgba(0,0,0,.35)] lg:text-[32px]">Explore Land in Lamu</h1>
            <p class="mt-1 max-w-[30rem] text-sm text-sand-50 [text-shadow:0_1px_2px_rgba(0,0,0,.35)]">Find coastal plots, acreage and development opportunities.</p>
        </div>
    </section>

    <div class="mx-auto grid w-full max-w-[1440px] gap-4 px-4 py-4 sm:px-6 lg:w-[90%] lg:grid-cols-[230px_minmax(0,1fr)] lg:px-0 xl:grid-cols-[230px_minmax(0,1fr)_280px]">
        <div class="contents lg:sticky lg:top-[60px] lg:row-span-2 lg:block lg:max-h-[calc(100dvh-72px)] lg:space-y-3 lg:self-start lg:overflow-y-auto lg:[scrollbar-width:thin] xl:row-span-1">
            <div class="sticky top-[65px] z-20 order-1 -mx-4 flex items-center justify-between gap-3 border-b border-sand-200 bg-sand-50/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 lg:hidden">
                <button type="button" data-dialog-open="land-filters-dialog" aria-controls="land-filters-dialog" aria-expanded="false" class="inline-flex h-10 items-center gap-2 rounded-md border border-lamu-800 bg-white px-4 text-sm font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5h14v2H3V5Zm3 4h8v2H6V9Zm2 4h4v2H8v-2Z" /></svg>
                    Filters
                    @if ($filterChips !== [])
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-lamu-800 px-1.5 text-xs text-white">{{ count($filterChips) }}<span class="sr-only"> applied</span></span>
                    @endif
                </button>
                <p class="text-sm text-stone-600">{{ $resultCount }} preview {{ \Illuminate\Support\Str::plural('listing', $resultCount) }}</p>
            </div>
            <dialog id="land-filters-dialog" class="public-mobile-menu lg:hidden" aria-labelledby="land-filters-dialog-title">
                <div class="flex items-center justify-between border-b border-sand-200 px-4 py-3">
                    <h2 id="land-filters-dialog-title" class="home-serif text-lg font-semibold text-lamu-900">Filters</h2>
                    <button type="button" data-dialog-close class="inline-flex size-10 items-center justify-center rounded-full hover:bg-lamu-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800" aria-label="Close filters"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-4 [&_input]:h-10 [&_input]:text-sm [&_select]:h-10 [&_select]:text-sm [&_button[type=submit]]:h-11 [&_button[type=submit]]:text-sm">
                    @include('public.land.filters')
                    @include('public.land.locations', ['headingId' => 'land-filters-locations-heading', 'navClass' => 'mt-5 border-t border-sand-200 pt-4', 'touch' => true])
                </div>
            </dialog>
            <div class="hidden rounded-lg border border-sand-200 bg-white p-3 shadow-sm shadow-stone-900/5 lg:block">
                @include('public.land.filters', ['showHeading' => true])
            </div>

            @include('public.land.locations', ['headingId' => 'land-locations-heading', 'navClass' => 'hidden rounded-lg border border-sand-200 bg-white p-3 shadow-sm shadow-stone-900/5 lg:block'])
        </div>

        <section aria-labelledby="land-results-heading" class="order-2 min-w-0 lg:order-none">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                    <div>
                        <h2 id="land-results-heading" class="home-serif text-lg font-semibold leading-tight text-stone-900">{{ $resultCount }} preview {{ \Illuminate\Support\Str::plural('listing', $resultCount) }}</h2>
                        <p class="text-xs text-stone-500">Preview listings — sample cards, not live offers.</p>
                    </div>
                    @if ($filterChips !== [])
                        <h3 class="sr-only">Applied filters</h3>
                        <ul class="flex flex-wrap items-center gap-2">
                            @foreach ($filterChips as $chip)
                                <li class="inline-flex items-center gap-1 rounded-md bg-lamu-100 py-0.5 pl-2.5 pr-0.5 text-xs text-lamu-900">
                                    <span>{{ $chip['label'] }}</span>
                                    <a href="{{ $chip['url'] }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md text-lamu-800 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800" aria-label="Remove filter: {{ $chip['label'] }}">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg>
                                    </a>
                                </li>
                            @endforeach
                            <li>
                                <a href="{{ $clearFiltersUrl }}" class="inline-flex min-h-7 items-center rounded-sm px-1 text-xs font-medium text-lamu-800 underline underline-offset-2 hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800">Clear all</a>
                            </li>
                        </ul>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="GET" action="{{ $directoryUrl }}" class="flex items-center gap-2">
                        @foreach (\Illuminate\Support\Arr::except($currentQuery, ['sort']) as $name => $value)
                            @if (is_string($value))
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label for="land-sort" class="text-xs text-stone-600">Sort:</label>
                        <select id="land-sort" name="sort" data-auto-submit class="h-8 rounded-md border border-sand-200 bg-white px-2 text-xs text-stone-900 focus:border-lamu-700 focus:outline-none focus:ring-2 focus:ring-lamu-100">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="h-8 rounded-md border border-sand-200 px-3 text-xs font-medium text-lamu-800">Sort</button></noscript>
                    </form>
                    <div class="inline-flex gap-2" role="group" aria-label="Results layout">
                        @foreach (['cards' => 'Grid', 'list' => 'List'] as $view => $label)
                            <a href="{{ $viewUrl($view) }}" @if ($filters['view'] === $view) aria-current="true" @endif @class([
                                'inline-flex h-8 items-center rounded-md border px-2.5 text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800',
                                'border-lamu-800 bg-lamu-800 font-medium text-white' => $filters['view'] === $view,
                                'border-sand-200 bg-white text-stone-700 hover:border-lamu-700' => $filters['view'] !== $view,
                            ])>
                                <svg class="mr-1 h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    @if ($view === 'cards')
                                        <path d="M3 3h6v6H3V3Zm8 0h6v6h-6V3ZM3 11h6v6H3v-6Zm8 0h6v6h-6v-6Z" />
                                    @else
                                        <path d="M3 4h2v2H3V4Zm4 0h10v2H7V4ZM3 9h2v2H3V9Zm4 0h10v2H7V9Zm-4 5h2v2H3v-2Zm4 0h10v2H7v-2Z" />
                                    @endif
                                </svg>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($previewListings->isEmpty())
                <div class="mt-3 rounded-lg border border-sand-200 bg-white p-5 text-sm text-stone-600">
                    <p class="font-medium text-stone-900">No preview listings match these filters.</p>
                    <p class="mt-1">Try widening the price or acreage range, or <a href="{{ $clearFiltersUrl }}" class="font-medium text-lamu-800 hover:underline">clear all filters</a>.</p>
                </div>
            @elseif ($filters['view'] === 'list')
                <div class="mt-3 space-y-3">
                    @foreach ($previewListings as $listing)
                        <x-land-preview-card :listing="$listing" layout="list" />
                    @endforeach
                </div>
            @else
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($previewListings as $listing)
                        <x-land-preview-card :listing="$listing" />
                    @endforeach
                </div>
            @endif

            @if ($previewListings->hasPages())
                <div class="mt-4 rounded-lg border border-sand-200 bg-white px-3 py-2">
                    {{ $previewListings->links() }}
                </div>
            @endif
        </section>

        <aside class="order-3 grid content-start gap-3 sm:grid-cols-2 lg:order-none lg:col-start-2 xl:sticky xl:top-[60px] xl:col-start-3 xl:max-h-[calc(100dvh-72px)] xl:grid-cols-1 xl:self-start xl:overflow-y-auto xl:[scrollbar-width:thin]" aria-label="Area overview and assistance">
            @if ($mapImage)
                <figure class="overflow-hidden rounded-lg border border-sand-200 bg-white shadow-sm shadow-stone-900/5">
                    <img src="{{ asset($mapImage) }}" alt="Overview map of the Lamu area" width="188" height="222" class="block h-auto w-full">
                    <figcaption class="border-t border-sand-200 px-3 py-1.5 text-xs font-medium text-lamu-900">Lamu area overview</figcaption>
                </figure>
            @endif

            <div @class(['flex gap-3 rounded-lg border border-gold-500/30 bg-[#f8f1e4] p-3', 'sm:col-span-2 xl:col-span-1' => ! $mapImage])>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gold-500/50 text-gold-500" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9" /><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z" stroke-linejoin="round" /></svg>
                </span>
                <div>
                    <h2 class="home-serif text-base font-semibold leading-snug text-stone-900">Looking for something specific?</h2>
                    <p class="mt-0.5 text-xs text-stone-600">Tell us your preferred location, budget and acreage.</p>
                    <a href="{{ $findLandHref }}" @if ($findLandModal) data-turbo-frame="modal" @endif class="mt-1 inline-flex min-h-7 items-center rounded-sm text-xs font-semibold text-gold-500 underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Find Land for Me <span aria-hidden="true" class="ml-1">→</span></a>
                </div>
            </div>
        </aside>
    </div>
</x-public-layout>
