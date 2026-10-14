@php
    $deskEmail = config('lamu.desk_email');
    $deskEmail = is_string($deskEmail) && filter_var($deskEmail, FILTER_VALIDATE_EMAIL) ? $deskEmail : null;
    $findLandHref = auth()->check() && auth()->user()->hasPermission('workspace.buyer')
        ? route('buyer.sourcing.create')
        : route('home').'#find-land';
    $buyerBriefHref = auth()->check() && auth()->user()->hasPermission('workspace.buyer')
        ? route('buyer.sourcing.create')
        : route('register');
    $buyerBriefLabel = auth()->check() && auth()->user()->hasPermission('workspace.buyer')
        ? 'Open saved search'
        : 'Save a search after you register';

    $places = collect();
    foreach ($counties as $county) {
        foreach ($county->localities as $place) {
            $places->push([
                'name' => $place->name,
                'count' => (int) $place->available_listings_count,
                'href' => route('land.index', [$county, $place]),
            ]);
        }
    }
    $places = $places->sortByDesc('count')->values();

    $slots = [
        ['needle' => 'mokowe', 'label' => 'Mokowe', 'category' => 'Beachfront', 'image' => 'images/prime_coustal.jpg', 'alt' => 'Coast near Mokowe'],
        ['needle' => 'hindi', 'label' => 'Hindi', 'category' => 'Riverside', 'image' => 'images/hind_island_development.jpg', 'alt' => 'Landscape near Hindi'],
        ['needle' => 'mpeketoni', 'label' => 'Mpeketoni', 'category' => 'Investment Opportunity', 'image' => 'images/mpeketoni_growth.jpg', 'alt' => 'Landscape near Mpeketoni'],
        ['needle' => 'witu', 'label' => 'Witu', 'category' => 'Large Acreage', 'image' => 'images/witu_mixed_island.jpg', 'alt' => 'Landscape near Witu'],
    ];
    $usedListingIds = [];
    $cards = [];
    foreach ($slots as $slot) {
        $listing = $featured->first(function ($property) use ($slot, $usedListingIds) {
            if (in_array($property->id, $usedListingIds, true)) {
                return false;
            }
            $haystack = strtolower(trim(
                ($property->title ?? '').' '.
                ($property->locality ?? '').' '.
                ($property->areaLocality->name ?? '')
            ));

            return str_contains($haystack, $slot['needle']);
        });
        if ($listing) {
            $usedListingIds[] = $listing->id;
        }
        $place = $places->first(fn (array $row): bool => str_contains(strtolower($row['name']), $slot['needle']));
        $cards[] = ['slot' => $slot, 'listing' => $listing, 'place' => $place];
    }
    $allCardsAreListings = collect($cards)->every(fn (array $card): bool => $card['listing'] !== null);
@endphp

<x-public-layout
    home
    title="Lamu Acres"
    description="Published land for sale and lease on the Lamu coast, with location guides and a separate investor research library."
>
    <x-slot:banner>
        <header class="home-header sticky top-0 z-30 border-b border-sand-200 bg-sand-50">
            <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between gap-3 px-4 lg:w-[85%] lg:px-0">
                <a href="{{ route('home') }}" class="min-w-0 shrink-0 leading-none">
                    <span class="home-serif block text-base font-semibold tracking-tight text-lamu-900">Lamu Acres</span>
                    <span class="mt-0.5 block text-[9px] tracking-[0.18em] text-stone-500 uppercase">Land · People · Opportunity</span>
                </a>
                <nav class="hidden items-center gap-x-4 text-[13px] text-stone-700 xl:flex" aria-label="Public">
                    <details class="relative">
                        <summary class="cursor-pointer list-none rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Explore Land</summary>
                        <div class="absolute left-0 z-20 mt-2 w-52 rounded-lg border border-sand-200 bg-white p-2 text-sm shadow-sm">
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index') }}">All published land</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease land</a>
                        </div>
                    </details>
                    <a class="rounded-sm hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" href="{{ $findLandHref }}">Find Land for Me</a>
                    <a class="rounded-sm hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" href="#intelligence">Investor Intelligence</a>
                    <a class="rounded-sm hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" href="{{ route('broker-partners') }}">Broker Partners</a>
                    <a class="rounded-sm hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" href="{{ route('list-property') }}">List Property</a>
                    @if ($deskEmail)
                        <a class="rounded-sm hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" href="mailto:{{ $deskEmail }}">Contact</a>
                    @endif
                </nav>
                <div class="flex items-center gap-2">
                    <a href="#home-search" class="inline-flex size-9 items-center justify-center rounded-md border border-sand-200 bg-white text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" aria-label="Search published land">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    </a>
                    @auth
                        <x-account-menu :role="$accountRole ?? ''" :workspace="$currentWorkspace ?? null" :workspaces="$availableWorkspaces ?? []" />
                    @else
                        <a href="{{ route('login') }}" class="inline-flex h-9 items-center rounded-md border border-lamu-900 px-3 text-xs font-medium text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Login / Account</a>
                    @endauth
                    <details class="relative xl:hidden">
                        <summary class="cursor-pointer list-none rounded-md border border-sand-200 bg-white px-3 py-1.5 text-xs font-medium text-lamu-900">Menu</summary>
                        <nav class="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-sand-200 bg-white p-2 text-sm shadow-sm" aria-label="Mobile">
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index') }}">Explore Land</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('land.index', ['purpose' => 'lease']) }}">Lease Land</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ $findLandHref }}">Find Land for Me</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="#intelligence">Investor Intelligence</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('broker-partners') }}">Broker Partners</a>
                            <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="{{ route('list-property') }}">List Property</a>
                            @if ($deskEmail)
                                <a class="block rounded-md px-3 py-2 hover:bg-sand-50" href="mailto:{{ $deskEmail }}">Contact</a>
                            @endif
                        </nav>
                    </details>
                </div>
            </div>
        </header>
    </x-slot:banner>

    <section class="home-hero relative isolate overflow-hidden bg-lamu-900 text-white lg:min-h-[280px]">
        <img
            src="{{ asset('images/hero_section.jpg') }}"
            alt=""
            width="1600"
            height="700"
            fetchpriority="high"
            decoding="async"
            class="absolute inset-0 h-full w-full object-cover object-[center_42%]"
        >
        <div class="absolute inset-0 bg-lamu-900/30" aria-hidden="true"></div>
        <div class="relative mx-auto flex w-full max-w-[1440px] flex-col items-center px-4 py-6 text-center lg:h-full lg:w-[85%] lg:justify-center lg:px-0 lg:py-4">
            <h1 class="home-serif max-w-4xl text-[1.75rem] leading-tight font-semibold text-balance sm:text-4xl lg:text-[2.5rem] lg:leading-[1.15]">Find Land for Sale in Lamu and Beyond</h1>
            <p class="mt-1 max-w-2xl text-sm text-sand-50 sm:text-base">Published land, property sourcing and investment research for the Lamu coast.</p>
            <form id="home-search" method="GET" action="{{ route('land.index') }}" class="mt-3 flex w-full max-w-[820px] flex-col gap-1.5 rounded-lg bg-white p-1.5 text-left text-stone-900 shadow-md sm:h-[50px] sm:flex-row sm:items-center sm:p-0 sm:px-1.5" role="search">
                <label class="sr-only" for="home-search-q">Search published land</label>
                <span class="pl-1.5 text-stone-400" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                </span>
                <input id="home-search-q" name="q" type="search" placeholder="Search by location, town, landmark or budget…" class="h-10 min-w-0 flex-1 bg-transparent px-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800">
                <a href="{{ route('land.index') }}" class="inline-flex size-10 shrink-0 items-center justify-center rounded-md border border-sand-200 text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800" aria-label="Open listing filters">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                </a>
                <button type="submit" class="h-10 shrink-0 rounded-md bg-lamu-900 px-4 text-sm font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Search</button>
            </form>
            <div class="mt-2.5 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-white sm:text-sm">
                <a class="inline-flex items-center gap-1 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" href="{{ route('land.index') }}">
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/></svg>
                    Published listings
                </a>
                <span aria-hidden="true" class="text-white/40">|</span>
                <a class="inline-flex items-center gap-1 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" href="#trust">
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 12l3 3 5-6"/><circle cx="12" cy="12" r="9"/></svg>
                    Staff review
                </a>
                <span aria-hidden="true" class="text-white/40">|</span>
                <a class="inline-flex items-center gap-1 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" href="#locations">
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2"/></svg>
                    Local guides
                </a>
                <span aria-hidden="true" class="text-white/40">|</span>
                <a class="inline-flex items-center gap-1 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" href="#partners">
                    <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zM3 19a5 5 0 0 1 10 0M13 19a5 5 0 0 1 8 0"/></svg>
                    Brokers and owners
                </a>
                <a href="{{ $findLandHref }}" class="inline-flex h-9 items-center rounded-md bg-gold-500 px-3 text-xs font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Find Land for Me</a>
            </div>
        </div>
    </section>

    <section id="featured" class="mx-auto w-full max-w-[1920px] px-4 py-3 lg:w-[95%] lg:px-0">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h2 class="home-serif text-xl text-stone-900 sm:text-2xl">{{ $allCardsAreListings ? 'Featured Land for Sale' : 'Explore Land by Location' }}</h2>
                <p class="text-sm text-stone-500">{{ $allCardsAreListings ? 'Handpicked opportunities in Lamu County' : 'Explore coastal locations. Listings appear as they become available.' }}</p>
            </div>
            <a href="{{ route('land.index', ['purpose' => 'sale']) }}" class="inline-flex items-center gap-2 text-sm font-medium text-lamu-800 hover:underline">View All Listings <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></a>
        </div>
        <div class="mt-2 grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                @php
                    $listing = $card['listing'];
                    $place = $card['place'];
                    $href = $listing?->publicUrl() ?? ($place['href'] ?? route('land.index', ['q' => $card['slot']['needle']]));
                    $title = $listing?->title ?? ($place['name'] ?? $card['slot']['label']);
                @endphp
                <article class="overflow-hidden rounded-lg border border-sand-200 bg-white shadow-sm">
                    <div class="home-listing-photo relative h-28 overflow-hidden sm:h-32">
                        <img src="{{ asset($card['slot']['image']) }}" alt="{{ $card['slot']['alt'] }}" class="h-full w-full object-cover" width="640" height="240" decoding="async" loading="lazy">
                        <span class="absolute top-3 left-3 max-w-[calc(100%-4.5rem)] rounded-md bg-lamu-900/95 px-3 py-1.5 text-[10px] leading-tight font-semibold tracking-wide text-white uppercase shadow-sm">{{ $card['slot']['category'] }}</span>
                        @if ($listing)
                            <a href="{{ $href }}#save-property" class="absolute top-2 right-2 inline-flex size-9 items-center justify-center rounded-full bg-black/20 text-white hover:bg-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" aria-label="Save {{ $title }}">
                                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
                            </a>
                        @endif
                    </div>
                    <div class="flex flex-col gap-1 px-3 py-2">
                        <h3 class="home-serif text-base leading-tight font-semibold text-stone-900">
                            <a href="{{ $href }}" class="hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">{{ $title }}</a>
                        </h3>
                        <p class="flex items-center gap-1.5 text-xs text-stone-600">
                            <svg viewBox="0 0 24 24" class="size-3.5 shrink-0 text-lamu-700" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2"/></svg>
                            {{ $listing?->locationLabel() ?? (($place['name'] ?? $card['slot']['label']).', Lamu County') }}
                        </p>
                        <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1.5">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-stone-800">
                                @if ($listing)
                                    <span class="inline-flex items-center gap-1.5"><svg viewBox="0 0 24 24" class="size-3.5 shrink-0 text-lamu-700" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 7 7-3 4 3 7-3v16l-7 3-4-3-7 3V7Zm7-3v16m4-13v16"/></svg>{{ $listing->acreage ? rtrim(rtrim(number_format((float) $listing->acreage, 2), '0'), '.').' Acres' : 'Size on request' }}</span>
                                    <span class="text-stone-300" aria-hidden="true">|</span>
                                    <span>{{ $listing->formattedPrice() ?? 'Price on request' }}</span>
                                @else
                                    <span>{{ $place ? $place['count'].' published '.($place['count'] === 1 ? 'listing' : 'listings') : 'Location guide' }}</span>
                                @endif
                            </p>
                            <a href="{{ $href }}" class="inline-flex h-8 shrink-0 items-center gap-2 rounded border border-gold-500/70 px-2.5 text-xs font-medium text-lamu-900 hover:bg-sand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">{{ $listing ? 'View Details' : 'Explore Location' }} <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="home-discovery-row mx-auto grid w-full max-w-[1920px] gap-3 px-4 pb-4 lg:w-[95%] lg:grid-cols-[minmax(0,1.43fr)_minmax(0,0.83fr)_minmax(0,1.06fr)] lg:px-0">
        <article id="locations" class="relative isolate overflow-hidden rounded-lg border border-sand-200 bg-sand-50 p-3">
            <img src="{{ asset('images/explore_by_location.jpg') }}" alt="" class="pointer-events-none absolute inset-0 -z-10 h-full w-full object-fill" width="1654" height="640" loading="lazy" decoding="async">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="home-serif text-base font-semibold text-stone-900">Explore by Location</h2>
                    <p class="text-[10px] leading-snug text-stone-500">Published counts by locality.</p>
                </div>
                <a href="{{ route('land.index') }}" class="shrink-0 rounded bg-sand-50 px-1 text-xs font-medium text-lamu-800 hover:underline">View All Locations →</a>
            </div>
            @if ($places->isEmpty())
                <p class="mt-3 text-sm text-stone-600">Location guides are not published yet.</p>
            @else
                <ul class="mt-2 w-fit min-w-[32%] rounded-md bg-white/90 p-1 text-xs shadow-sm">
                    @foreach ($places->take(5) as $place)
                        <li>
                            <a href="{{ $place['href'] }}" class="flex min-h-7 items-center justify-between gap-3 rounded-md px-1.5 hover:bg-sand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800">
                                <span class="inline-flex items-center gap-1.5 text-stone-800"><svg viewBox="0 0 24 24" class="size-4 shrink-0 rounded-full bg-lamu-100 p-0.5 text-lamu-700" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2"/></svg>{{ $place['name'] }}</span>
                                <span class="text-[10px] text-stone-500">{{ $place['count'] }} {{ $place['count'] === 1 ? 'listing' : 'listings' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>

        <article id="find-land" class="flex flex-col rounded-lg border border-sand-200 bg-sand-50 p-3">
            <h2 class="home-serif text-base font-semibold text-stone-900">Find Land for Me</h2>
            <p class="mt-1 text-[10px] leading-snug text-stone-500">Search published land by place, budget, and size.</p>
            <form method="GET" action="{{ route('land.index') }}" class="mt-2 grid flex-1 content-start grid-cols-2 gap-x-2 gap-y-1 text-xs">
                <label class="col-span-1">
                    <span class="text-[10px] font-medium text-stone-700">Preferred location</span>
                    <input name="q" type="search" class="mt-0.5 h-7 w-full rounded-md border border-sand-200 bg-white px-2 text-xs">
                </label>
                <label>
                    <span class="text-[10px] font-medium text-stone-700">Min budget</span>
                    <input name="min_price" inputmode="decimal" aria-label="Minimum budget in Kenyan shillings" class="mt-0.5 h-7 w-full rounded-md border border-sand-200 bg-white px-2 text-xs">
                </label>
                <label>
                    <span class="text-[10px] font-medium text-stone-700">Max budget</span>
                    <input name="max_price" inputmode="decimal" aria-label="Maximum budget in Kenyan shillings" class="mt-0.5 h-7 w-full rounded-md border border-sand-200 bg-white px-2 text-xs">
                </label>
                <label class="col-span-1">
                    <span class="text-[10px] font-medium text-stone-700">Minimum acreage</span>
                    <input name="min_acres" inputmode="decimal" class="mt-0.5 h-7 w-full rounded-md border border-sand-200 bg-white px-2 text-xs">
                </label>
                <button type="submit" class="col-span-2 h-7 rounded-md bg-lamu-900 text-sm font-medium text-white">Search published land</button>
            </form>
            <a href="{{ $buyerBriefHref }}" class="mt-1 text-center text-[10px] font-medium text-lamu-800 hover:underline">{{ $buyerBriefLabel }}</a>
        </article>

        <article id="intelligence" class="overflow-hidden rounded-lg border border-sand-200 bg-sand-50">
            <div class="grid h-full md:grid-cols-[1.65fr_0.85fr]">
                <div class="flex flex-col p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="home-serif text-base font-semibold text-stone-900">Investor Intelligence</h2>
                        <span class="rounded bg-gold-500 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-white">PREMIUM</span>
                    </div>
                    <p class="mt-1 text-[10px] leading-snug text-stone-500">Published research, separate from the public directory.</p>
                    <ul class="mt-2 space-y-2 text-[11px] leading-snug text-stone-700">
                        <li><a class="hover:underline" href="{{ route('intelligence.planning.index') }}">Planning & infrastructure</a></li>
                        <li><a class="hover:underline" href="{{ route('intelligence.imagery.index') }}">Satellite imagery comparisons</a></li>
                        <li><a class="hover:underline" href="{{ route('intelligence.projects.index') }}">Development tracking</a></li>
                        <li><a class="hover:underline" href="{{ route('intelligence.prices') }}">Market insights & asking prices</a></li>
                    </ul>
                    <a href="{{ route('intelligence.projects.index') }}" class="mt-3 inline-flex h-7 w-fit items-center rounded-md bg-gold-500 px-3 text-xs font-medium text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Explore Investor Pass</a>
                </div>
                <div class="relative min-h-28">
                    <img src="{{ asset('images/investor_intelligence.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-right" width="640" height="420" decoding="async">
                </div>
            </div>
        </article>
    </section>

    <section id="trust" class="mx-auto grid w-full max-w-[1440px] items-center gap-3 px-4 pb-4 lg:w-[85%] lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:px-0">
        <div>
            <h2 class="home-serif text-xl text-stone-900">Why Choose Lamu Acres</h2>
            <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex gap-2">
                    <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-lamu-100 text-lamu-900" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-lamu-900">Published listings</h3>
                        <p class="text-xs leading-snug text-stone-600">Listings stay on the directory while they are published and available.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-lamu-100 text-lamu-900" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 12l3 3 5-6"/><circle cx="12" cy="12" r="9"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-lamu-900">Staff review</h3>
                        <p class="text-xs leading-snug text-stone-600">Submissions are reviewed before they can be published.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-lamu-100 text-lamu-900" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-lamu-900">Location guides</h3>
                        <p class="text-xs leading-snug text-stone-600">Browse by county and locality, not parcel boundaries.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <span class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-lamu-100 text-lamu-900" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V5M4 19h16M8 16v-5M12 16V8M16 16v-3"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-lamu-900">Separate research</h3>
                        <p class="text-xs leading-snug text-stone-600">Investor research sits outside the public directory.</p>
                    </div>
                </div>
            </div>
        </div>
        <div id="partners" class="rounded-lg border border-gold-500/40 bg-[#f6f0e2] px-4 py-3">
            <h2 class="home-serif text-lg text-stone-900">Are You a Broker or Land Owner?</h2>
            <p class="mt-1 text-sm text-stone-600">Apply for broker access or start a seller listing. An account does not publish land by itself.</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('broker-partners') }}" class="inline-flex h-9 items-center rounded-md bg-gold-500 px-3 text-xs font-medium text-white">Broker Partners</a>
                <a href="{{ route('list-property') }}" class="inline-flex h-9 items-center rounded-md border border-gold-500 bg-white/70 px-3 text-xs font-medium text-stone-800">List Property</a>
            </div>
        </div>
    </section>

    <x-slot:closing>
        <footer class="home-footer bg-lamu-900 text-sand-50">
            <div class="mx-auto grid w-full max-w-[1440px] gap-5 px-4 py-5 text-xs sm:grid-cols-2 lg:w-[85%] lg:grid-cols-5 lg:px-0">
                <div>
                    <p class="home-serif text-base text-white">Lamu Acres</p>
                    <p class="mt-1.5 text-sand-200">Published land listings and location guides for the Lamu coast.</p>
                </div>
                <nav aria-label="Properties">
                    <p class="font-medium text-gold-500">Properties</p>
                    <a class="mt-1.5 block hover:text-white" href="{{ route('land.index') }}">Published land</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('land.index', ['purpose' => 'sale']) }}">For sale</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('land.index', ['purpose' => 'lease']) }}">For lease</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('list-property') }}">List property</a>
                </nav>
                <nav aria-label="Investor intelligence">
                    <p class="font-medium text-gold-500">Investor Intelligence</p>
                    <a class="mt-1.5 block hover:text-white" href="{{ route('intelligence.projects.index') }}">Developments</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('intelligence.imagery.index') }}">Imagery</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('intelligence.planning.index') }}">Planning</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('intelligence.prices') }}">Prices</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('intelligence.reports.index') }}">Reports</a>
                </nav>
                <nav aria-label="Brokers and company">
                    <p class="font-medium text-gold-500">For Brokers</p>
                    <a class="mt-1.5 block hover:text-white" href="{{ route('broker-partners') }}">Broker Partners</a>
                    <p class="mt-3 font-medium text-gold-500">Company</p>
                    <a class="mt-1.5 block hover:text-white" href="{{ route('home') }}">Home</a>
                    <a class="mt-1 block hover:text-white" href="{{ route('login') }}">Login</a>
                    @if ($deskEmail)
                        <a class="mt-1 block hover:text-white" href="mailto:{{ $deskEmail }}">Contact</a>
                    @endif
                </nav>
                <div>
                    <p class="font-medium text-gold-500">Updates</p>
                    <p class="mt-1.5 text-sand-200">New listings appear in the public directory. This site does not run a newsletter form.</p>
                </div>
            </div>
            <div class="border-t border-white/10">
                <p class="mx-auto w-full max-w-[1440px] px-4 py-2.5 text-[11px] text-sand-200 lg:w-[85%] lg:px-0">© {{ now()->year }} Lamu Acres</p>
            </div>
        </footer>
    </x-slot:closing>
</x-public-layout>
