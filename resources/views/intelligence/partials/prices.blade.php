<p class="mt-4 max-w-3xl text-sm text-stone-600">Figures below are asking prices from published listings. They are not achieved sale prices, and they are not a forecast. Seller identity and parcel numbers are not part of this observatory.</p>
<x-filter-bar :action="url()->current()" class="mt-4 grid gap-3 rounded-lg border border-sand-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="price-locality" class="block text-xs font-medium text-stone-700">Location</label>
        <select id="price-locality" name="locality" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end gap-3">
        <button type="submit" class="min-h-10 rounded-md bg-lamu-900 px-4 py-2 text-xs font-semibold text-white">Apply filters</button>
        <a href="{{ url()->current() }}" class="inline-flex min-h-10 items-center text-xs font-medium text-lamu-800 underline">Reset</a>
    </div>
</x-filter-bar>
<div class="mt-5 flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="home-serif text-lg font-semibold text-lamu-900">Asking prices by location</h2>
    <p class="text-xs text-stone-600">{{ $rows->count() }} locations matching your selection</p>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($rows as $row)
        <article class="flex flex-col rounded-lg border border-sand-200 bg-white p-4">
            <h3 class="home-serif text-base font-semibold text-lamu-900">{{ $row['locality']->name }}</h3>
            <p class="mt-2 text-xs leading-relaxed text-stone-600">Sample size: {{ $row['sample'] }} published asking {{ $row['sample'] === 1 ? 'price' : 'prices' }}. Freshness: {{ $row['fresh_at'] ? \Illuminate\Support\Carbon::parse($row['fresh_at'])->toFormattedDateString() : 'not recorded' }}.</p>
            @if ($premium)
                @if ($row['median'] !== null)
                    <p class="mt-2 text-sm font-medium text-lamu-900">Median asking price per acre: KSh {{ number_format($row['median']) }}</p>
                @else
                    <p class="mt-2 text-sm text-amber-900">Insufficient observations for a reliable locality median.</p>
                    <p class="text-xs text-stone-600">At least {{ $row['min_median_sample'] ?? config('intelligence.price_observatory.min_median_sample', 3) }} published asking prices are required.</p>
                @endif
                <h3 class="mt-4 text-sm font-medium text-lamu-900">Asking-price trend</h3>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @forelse ($row['history'] as $point)
                        <li>{{ $point['label'] }} · KSh {{ number_format($point['amount']) }} @if ($point['at']) · {{ \Illuminate\Support\Carbon::parse($point['at'])->toFormattedDateString() }} @endif</li>
                    @empty
                        <li>No earlier asking price has been recorded.</li>
                    @endforelse
                </ul>
                <h3 class="mt-4 text-sm font-medium text-lamu-900">Comparable published listings</h3>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($row['listings'] as $listing)
                        <li>{{ $listing->title }} · asking KSh {{ number_format((int) $listing->pricePerAcre()) }} per acre</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-stone-700">The median asking price and comparable listings are part of an active Investor Pass.</p>
            @endif
        </article>
    @empty
        <p class="rounded-lg border border-sand-200 bg-white p-5 text-sm text-stone-600 sm:col-span-2 lg:col-span-3">No published asking prices are available. No median has been calculated.</p>
    @endforelse
</div>
@if ($premium)
    <h2 class="mt-8 font-semibold text-lamu-900">Verified sales</h2>
    <p class="mt-2 max-w-3xl text-sm text-stone-600">A verified sale is evidence of a completed transaction. It is not mixed into the asking-price median.</p>
    <ul class="mt-3 list-disc pl-5 text-sm">
        @forelse ($sales as $sale)
            <li>Verified sale · {{ $sale->locality?->name }} · KSh {{ number_format($sale->price_amount) }} on {{ $sale->acreage }} acres · {{ $sale->sold_on->toFormattedDateString() }} · {{ $sale->source_name }}</li>
        @empty
            <li>No verified sale has been published.</li>
        @endforelse
    </ul>
@endif
