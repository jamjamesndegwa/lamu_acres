@php
    $mode = $input['mode'] ?? (isset($input['latitude']) ? 'nearby' : 'area');
    $sort = $input['sort'] ?? ($centre ? 'nearest' : 'newest');
    $queryUrl = fn (array $changes) => route('find-land.index', array_filter(array_replace(\Illuminate\Support\Arr::except($input, ['page']), $changes), fn ($value) => $value !== null && $value !== ''));
@endphp
<x-public-layout title="Find Your Ideal Land — Lamu Acres" description="Discover available published land by location, distance, budget and acreage.">
    <section class="relative isolate overflow-hidden bg-lamu-900 text-white">
        <img src="{{ asset('images/explore_land_hero_section.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-[70%_50%]" fetchpriority="high">
        <div class="land-hero-fade absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
            <p class="text-xs font-semibold uppercase tracking-widest text-sand-200">Explore your next opportunity</p>
            <h1 class="home-serif mt-2 text-3xl font-semibold sm:text-4xl">Find Your Ideal Land</h1>
            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-sand-50">Discover land in your preferred location, explore nearby opportunities and compare available properties based on your budget and needs.</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">Please check your search</p>
                <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form id="find-land-form" action="{{ route('find-land.index') }}" method="GET" class="rounded-2xl border border-sand-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block text-sm font-medium">Preferred county
                    <select id="finder-county" name="county" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">All registered counties</option>
                        @foreach ($counties as $place)<option value="{{ $place->id }}" @selected((string) old('county', $county?->id) === (string) $place->id)>{{ $place->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">Preferred locality
                    <select id="finder-locality" name="locality" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">All registered localities</option>
                        @foreach ($counties as $place)
                            <optgroup label="{{ $place->name }}" data-county="{{ $place->id }}">
                                @foreach ($place->localities as $area)<option value="{{ $area->id }}" @selected((string) old('locality', $locality?->id) === (string) $area->id)>{{ $area->name }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-medium">Location search
                    <select id="finder-mode" name="mode" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="area" @selected(old('mode', $mode) === 'area')>Within county / locality</option>
                        <option value="nearby" @selected(old('mode', $mode) === 'nearby')>Nearby, across area boundaries</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Nearby radius (km)
                    <input type="number" name="radius" min="1" max="{{ config('lamu.find_land_max_radius_km') }}" step="1" value="{{ old('radius', $radius) }}" list="finder-radii" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 px-3 py-2">
                    <datalist id="finder-radii">@foreach ([5, 10, 25, 50, 100] as $km)<option value="{{ $km }}">{{ $km }} km</option>@endforeach</datalist>
                </label>
            </div>
            <section class="mt-5" aria-labelledby="finder-map-heading">
                <h2 id="finder-map-heading" class="home-serif text-xl font-semibold text-lamu-900">Choose your preferred location on the map</h2>
                <p class="mt-2 text-sm text-stone-600">Click or tap the map to place your search pin. Drag it to adjust the location, choose a radius, then press Find land.</p>
                <input type="hidden" id="finder-latitude" name="latitude" value="{{ old('latitude', $input['latitude'] ?? '') }}">
                <input type="hidden" id="finder-longitude" name="longitude" value="{{ old('longitude', $input['longitude'] ?? '') }}">
                <div id="finder-map" class="relative z-0 mt-3 h-80 w-full rounded-xl border border-sand-200" aria-label="Choose a preferred location on the map"></div>
                <p id="finder-map-status" role="status" aria-live="polite" class="mt-3 text-sm text-stone-600">Loading map. You can also search using the county and locality selectors.</p>
                <button type="button" id="finder-clear-point" class="mt-2 min-h-11 text-sm font-medium text-lamu-800 underline">Clear map selection and search selected area</button>
                <p class="mt-2 text-xs text-stone-500">Listing markers show approximate public locations for the current results page, not parcel boundaries.</p>
                <noscript><p class="mt-3 text-sm text-stone-600">Enable JavaScript to select a point on the map, or use county/locality search above.</p></noscript>
            </section>
            <details class="mt-4 border-t border-sand-200 pt-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-lamu-900">Refine by budget, acreage and property type</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-sm font-medium">Sale or lease
                        <select name="purpose" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                            <option value="">Sale and lease</option>
                            @foreach (\App\Enums\ListingPurpose::cases() as $purpose)<option value="{{ $purpose->value }}" @selected(old('purpose', $input['purpose'] ?? '') === $purpose->value)>{{ $purpose->label() }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium">Property type<select name="type" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2"><option value="">Any type</option>@foreach (\App\Enums\PropertyType::cases() as $type)<option value="{{ $type->value }}" @selected(old('type', $input['type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                    <label class="text-sm font-medium">Budget basis<select name="budget_basis" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2"><option value="total" @selected(old('budget_basis', $input['budget_basis'] ?? 'total') === 'total')>Whole offered area</option><option value="per_acre" @selected(old('budget_basis', $input['budget_basis'] ?? '') === 'per_acre')>Per acre</option></select></label>
                    <label class="text-sm font-medium">Keyword<input type="search" name="q" maxlength="100" value="{{ old('q', $filters['q']) }}" placeholder="Property name or location" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
                    @foreach (['min_price' => 'Minimum budget (KES)', 'max_price' => 'Maximum budget (KES)', 'min_acres' => 'Minimum acres', 'max_acres' => 'Maximum acres'] as $field => $label)
                        <label class="text-sm font-medium">{{ $label }}<input type="number" name="{{ $field }}" min="0" step="{{ str_contains($field, 'acres') ? '0.0001' : '1' }}" value="{{ old($field, $input[$field] ?? '') }}" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
                    @endforeach
                    <label class="text-sm font-medium">Sort results<select name="sort" class="mt-2 min-h-11 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">@foreach (['newest' => 'Newest', 'nearest' => 'Nearest', 'price_asc' => 'Budget: low to high', 'price_desc' => 'Budget: high to low', 'acres_asc' => 'Acreage: small to large', 'acres_desc' => 'Acreage: large to small'] as $value => $label)<option value="{{ $value }}" @selected(old('sort', $sort) === $value)>{{ $label }}</option>@endforeach</select></label>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-stone-600">Choose sale or lease when comparing budgets. Sale budgets compare the asking price of the whole offered area or its per-acre equivalent. Lease budgets compare monthly rent, converting annual rent to a monthly equivalent. Unknown rent periods and non-KES prices are excluded from budget filters. These are asking prices, not completed transactions.</p>
            </details>
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
            <div class="mt-5 flex flex-wrap items-center gap-4">
                <button type="submit" class="min-h-11 rounded-full bg-lamu-900 px-6 py-3 text-sm font-semibold text-white">Find land</button>
                <a href="{{ route('find-land.index') }}" class="min-h-11 content-center text-sm font-medium text-lamu-800 underline">Reset filters</a>
            </div>
        </form>

        <section class="mt-8" aria-labelledby="finder-results-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="finder-results-heading" class="home-serif text-2xl font-semibold text-lamu-900">{{ $listings->total() }} available {{ $listings->total() === 1 ? 'listing' : 'listings' }}</h2>
                    <p class="mt-1 text-sm text-stone-600">{{ $centre ? 'Within '.$radius.' km of '.$centre['label'].'. Approximate distances use public map points, not surveyed parcel boundaries.' : 'Published land'.($locality ? ' in '.$locality->name : ($county ? ' in '.$county->name : ' across registered locations')).'.' }}</p>
                </div>
                <div class="flex gap-2" aria-label="Results layout">@foreach (['cards' => 'Grid', 'list' => 'List'] as $value => $label)<a href="{{ $queryUrl(['view' => $value]) }}" @if ($filters['view'] === $value) aria-current="true" @endif class="min-h-11 rounded-full border border-sand-200 bg-white px-4 py-3 text-sm font-medium text-lamu-800">{{ $label }}</a>@endforeach</div>
            </div>
            @if ($listings->isEmpty())
                <div class="mt-5 rounded-2xl border border-sand-200 bg-white p-6">
                    <h3 class="font-semibold text-lamu-900">{{ $centre ? 'No available land was found within this distance.' : 'No available land matches these filters.' }}</h3>
                    <p class="mt-2 text-sm text-stone-600">Adjust your price or acreage range, choose another location, or clear your filters. Listings without a valid public map point cannot appear in nearby results.</p>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm font-medium text-lamu-800">
                        @if ($centre && $radius < config('lamu.find_land_max_radius_km'))<a class="underline" href="{{ $queryUrl(['radius' => min(config('lamu.find_land_max_radius_km'), $radius * 2)]) }}">Expand search radius</a>@endif
                        <a class="underline" href="{{ route('find-land.index') }}">Clear filters</a>
                        <a class="underline" href="{{ route('land.index') }}">Browse all published land</a>
                    </div>
                </div>
            @else
                <div @class(['mt-5 grid gap-4', 'sm:grid-cols-2 lg:grid-cols-3' => $filters['view'] !== 'list'])>
                    @foreach ($listings as $listing)<x-land-preview-card :listing="$listing" :layout="$filters['view'] === 'list' ? 'list' : 'grid'" />@endforeach
                </div>
                <div class="mt-6">{{ $listings->links() }}</div>
            @endif
        </section>

        <div class="mt-8">
            <section class="rounded-2xl border border-sand-200 bg-white p-5" aria-labelledby="finder-intelligence-heading">
                <h2 id="finder-intelligence-heading" class="home-serif text-xl font-semibold text-lamu-900">About this location</h2>
                <p class="mt-2 text-sm text-stone-600">{{ $locality?->name ?? $county?->name ?? 'Choose a county or locality for relevant published references.' }}@if ($locality) · {{ $county->name }}@endif</p>
                <p class="mt-2 text-sm text-stone-600">{{ $listings->total() }} available listings match the full search. Nearby results may cross county or locality boundaries.</p>
                @if ($plans->isNotEmpty() || $reports->isNotEmpty())
                    <ul class="mt-4 space-y-3 text-sm text-lamu-800">
                        @foreach ($plans as $plan)<li><a class="font-medium underline" href="{{ route('intelligence.planning.show', $plan) }}">{{ $plan->title }}</a> · {{ $plan->instrument_status->label() }}</li>@endforeach
                        @foreach ($reports as $report)<li><a class="font-medium underline" href="{{ route('intelligence.reports.show', $report) }}">{{ $report->title }}</a></li>@endforeach
                    </ul>
                    <p class="mt-3 text-xs text-stone-600">Published references for the selected administrative area. Confirm applicability to a parcel; research does not guarantee planning permission or investment returns.</p>
                @elseif ($county)
                    <p class="mt-3 text-sm text-stone-600">No published planning or research references are available for this selection.</p>
                @endif
            </section>
        </div>
        <section class="mt-8 rounded-2xl bg-lamu-900 p-6 text-white">
            <h2 class="home-serif text-xl font-semibold">Can't find suitable land? Let us find it for you</h2>
            <p class="mt-2 text-sm text-sand-50">Send the Lamu Acres desk your requirements through the existing buyer sourcing service.</p>
            <a href="{{ route('buyer.sourcing.create') }}" data-turbo-frame="_top" class="mt-4 inline-flex min-h-11 items-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-lamu-900">Request staff assistance</a>
        </section>
    </div>
    @include('public.find-land-controls')
</x-public-layout>
