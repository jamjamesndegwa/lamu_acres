<p class="mt-4 max-w-3xl text-sm text-stone-600">Figures below are asking prices from published listings. They are not achieved sale prices, and they are not a forecast. Seller identity and parcel numbers are not part of this observatory.</p>
<form method="GET" action="{{ url()->current() }}" class="mt-6 flex flex-wrap items-end gap-3">
    <div>
        <label for="price-locality" class="block text-sm font-medium">Location</label>
        <select id="price-locality" name="locality" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Filter</button>
</form>
<div class="mt-6 grid gap-4">
    @forelse ($rows as $row)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">{{ $row['locality']->name }}</h2>
            <p class="mt-2 text-sm text-stone-600">Sample size: {{ $row['sample'] }} published asking {{ $row['sample'] === 1 ? 'price' : 'prices' }}. Freshness: {{ $row['fresh_at'] ? \Illuminate\Support\Carbon::parse($row['fresh_at'])->toFormattedDateString() : 'not recorded' }}.</p>
            @if ($premium)
                <p class="mt-2">Median asking price per acre: KSh {{ number_format($row['median']) }}</p>
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
        <p class="text-stone-600">No published asking prices are available. No median has been calculated.</p>
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
