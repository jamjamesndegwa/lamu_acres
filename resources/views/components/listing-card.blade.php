@props(['listing'])

<article class="overflow-hidden rounded-2xl border border-sand-200 bg-white">
    <div class="border-b border-sand-200 bg-lamu-100 px-5 py-4">
        <p class="text-xs font-medium uppercase tracking-wide text-gold-500">{{ $listing->purpose?->label() }} · {{ $listing->property_type?->label() }}</p>
        <h3 class="mt-1 text-lg font-semibold text-lamu-900">
            <a href="{{ $listing->publicUrl() }}" class="hover:underline">{{ $listing->title }}</a>
        </h3>
    </div>
    <div class="space-y-2 px-5 py-4 text-sm text-stone-700">
        <p>{{ $listing->locationLabel() }}</p>
        @if ($listing->acreage)
            <p>{{ rtrim(rtrim(number_format((float) $listing->acreage, 2), '0'), '.') }} acres</p>
        @endif
        <p class="font-medium text-lamu-900">{{ $listing->formattedPrice() ?? 'Price on request' }}</p>
        @if ($listing->pricePerAcre())
            <p>KSh {{ number_format($listing->pricePerAcre()) }} per acre</p>
        @endif
    </div>
</article>
