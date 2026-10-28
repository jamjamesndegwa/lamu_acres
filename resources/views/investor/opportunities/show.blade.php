@php
    $locality = $property->areaLocality;
    $priceRow = $intelligence['price'];
@endphp

<x-workspace-layout :heading="$listing['title']">
    <p class="mt-2 text-sm text-stone-600">
        <a href="{{ route('investor.opportunities.index') }}" class="text-lamu-800 hover:underline">Investment Opportunities</a>
        @if ($property->areaCounty)
            · <a href="{{ route('investor.opportunities.index', $property->areaCounty) }}" class="text-lamu-800 hover:underline">{{ $property->areaCounty->name }}</a>
        @endif
        @if ($locality)
            · <a href="{{ route('investor.opportunities.index', [$property->areaCounty, $locality]) }}" class="text-lamu-800 hover:underline">{{ $locality->name }}</a>
        @endif
    </p>

    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-8">
            <section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-stone-500">Property summary</h2>
                <p class="mt-2 text-sm text-stone-600">{{ $listing['location'] }}</p>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-stone-500">Asking price</dt>
                        <dd class="home-serif text-xl font-semibold text-gold-600">{{ $listing['price'] ?? ($listing['is_lease'] ? 'Rent on request' : 'Price on request') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-stone-500">Price per acre</dt>
                        <dd class="font-medium text-lamu-900">{{ $listing['price_per_acre'] ? 'KES '.number_format($listing['price_per_acre']).' /acre' : 'Not stated' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-stone-500">Available acreage</dt>
                        <dd class="font-medium text-lamu-900">{{ $listing['acreage'] }} acres</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-stone-500">Sale / lease</dt>
                        <dd class="font-medium text-lamu-900">{{ $listing['purpose'] }} · {{ $listing['availability'] }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-sm text-stone-600">{{ $listing['summary'] ?: 'Open the public listing for photographs, verification checks and full property details.' }}</p>
                <a href="{{ $property->publicUrl() }}" class="mt-4 inline-flex text-sm font-semibold text-lamu-800 hover:underline">View public property details →</a>
                @if ($locality)
                    <div class="mt-4">
                        <x-investor-watch-control
                            :watch="$localityWatch"
                            :locality-id="$locality->id"
                            label="Follow this location"
                            watching-label="Following location"
                        />
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Area price intelligence</h2>
                <p class="mt-1 text-sm text-stone-600">Median and sample size use published asking prices only. Verified sale prices are shown separately on Land Prices.</p>
                @if ($priceRow)
                    <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-stone-500">Locality</dt>
                            <dd class="font-medium">{{ $priceRow['locality']->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Sample size</dt>
                            <dd class="font-medium">{{ $priceRow['sample'] }} asking {{ \Illuminate\Support\Str::plural('listing', $priceRow['sample']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Median per acre</dt>
                            <dd class="font-medium">
                                @if ($priceRow['median'] !== null)
                                    KES {{ number_format($priceRow['median']) }} /acre
                                @else
                                    Insufficient observations for a reliable locality median
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($priceRow['fresh_at'])
                        <p class="mt-2 text-xs text-stone-500">Latest listing observation {{ $priceRow['fresh_at']->diffForHumans() }}.</p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-stone-600">No published asking-price observations are recorded for this locality yet.</p>
                @endif
                @if ($locality)
                    <a href="{{ route('investor.prices', ['locality' => $locality->id]) }}" class="mt-4 inline-flex text-sm font-semibold text-lamu-800 hover:underline">Open Land Prices →</a>
                @endif
            </section>

            @include('investor.opportunities.partials.project-list', [
                'heading' => 'Infrastructure in this locality',
                'intro' => 'Released development projects linked to this locality.',
                'projects' => $intelligence['projects_locality'],
            ])

            @include('investor.opportunities.partials.project-list', [
                'heading' => 'County-level infrastructure context',
                'intro' => 'Broader county records—not inferred as nearby to this parcel.',
                'projects' => $intelligence['projects_county'],
                'countyContext' => true,
            ])

            @include('investor.opportunities.partials.planning-list', [
                'heading' => 'Planning and zoning in this locality',
                'records' => $intelligence['planning_locality'],
            ])

            @include('investor.opportunities.partials.planning-list', [
                'heading' => 'County-level planning context',
                'records' => $intelligence['planning_county'],
                'countyContext' => true,
            ])

            <section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Satellite intelligence</h2>
                <p class="mt-1 text-sm text-stone-600">Released imagery comparisons for this locality. Licensed files open from the comparison view.</p>
                @forelse ($intelligence['imagery'] as $comparison)
                    <article class="mt-4 border-t border-sand-100 pt-4 first:mt-0 first:border-0 first:pt-0">
                        <h3 class="font-medium text-lamu-900"><a href="{{ route('investor.imagery.show', $comparison) }}" class="hover:underline">{{ $comparison->area_label }}</a></h3>
                        <p class="text-xs text-stone-500">
                            @if ($comparison->beforeScene?->acquired_on && $comparison->afterScene?->acquired_on)
                                {{ $comparison->beforeScene->acquired_on->toFormattedDateString() }} → {{ $comparison->afterScene->acquired_on->toFormattedDateString() }}
                            @endif
                        </p>
                        @if ($comparison->observed_change)
                            <p class="mt-1 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit($comparison->observed_change, 200) }}</p>
                        @endif
                    </article>
                @empty
                    <p class="mt-3 text-sm text-stone-600">No released comparisons are linked to this locality.</p>
                @endforelse
            </section>

            <section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Exclusive research</h2>
                <p class="mt-1 text-sm text-stone-600">Published reports tied to this locality or its infrastructure projects.</p>
                @forelse ($intelligence['reports'] as $report)
                    <article class="mt-4 border-t border-sand-100 pt-4 first:mt-0 first:border-0 first:pt-0">
                        <h3 class="font-medium text-lamu-900"><a href="{{ route('investor.reports.show', $report) }}" class="hover:underline">{{ $report->title }}</a></h3>
                        <p class="text-xs text-stone-500">{{ $report->report_date?->toFormattedDateString() ?: $report->published_at?->toFormattedDateString() }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ $report->summary }}</p>
                    </article>
                @empty
                    <p class="mt-3 text-sm text-stone-600">No published research is linked to this location yet.</p>
                @endforelse
            </section>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <div class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
                <h2 class="home-serif text-lg font-semibold text-lamu-900">Next steps</h2>
                <p class="mt-2 text-sm text-stone-600">Use the same enquiry, purchase and visit flows as the public listing.</p>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="{{ route('land.enquiries.create', $property) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-4 py-2.5 text-center text-sm font-medium text-white">Enquire</a>
                    @unless ($listing['is_lease'])
                        <a href="{{ route('land.purchase-requests.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2.5 text-center text-sm font-medium text-lamu-900">I'm interested in buying</a>
                    @endunless
                    <a href="{{ route('land.visits.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2.5 text-center text-sm font-medium text-lamu-900">Book site visit</a>
                    @if (auth()->user()?->hasPermission('workspace.buyer'))
                        @if ($saved)
                            <p class="text-center text-xs text-stone-600">Saved to your buyer list.</p>
                        @else
                            <form method="POST" action="{{ route('land.saved.store', $property) }}">
                                @csrf
                                <button type="submit" class="w-full rounded-full border border-sand-200 px-4 py-2.5 text-sm font-medium text-lamu-800">Save property</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-800">
                Intelligence on this page supports due diligence. Planning records are not legal clearance for this property. Confirm zoning and approvals with the responsible authority.
            </div>
        </aside>
    </div>
</x-workspace-layout>
