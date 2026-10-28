@props(['match'])

@php
    $property = $match->property;
    $card = app(\App\Services\Catalog\PublicListing::class)->card($property);
    $imageSrc = str_starts_with($card['image'], 'images/')
        ? asset($card['image'])
        : $card['image'];
    $acres = rtrim(rtrim(number_format((float) $card['acres'], 2), '0'), '.');
    $reasons = trim(implode(' ', $match->reasons ?? []));
@endphp

<article {{ $attributes->class(['group flex h-full flex-col overflow-hidden rounded-2xl border border-sand-200 bg-white shadow-sm shadow-stone-900/5 transition hover:border-lamu-400 hover:shadow-md']) }}>
    <a href="{{ $card['url'] }}" class="relative block aspect-[5/3] overflow-hidden bg-sand-100">
        <img
            src="{{ $imageSrc }}"
            alt=""
            loading="lazy"
            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
        >
        <span class="absolute left-3 top-3 rounded-full bg-lamu-900 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-white shadow-sm">
            {{ $match->band->label() }}
        </span>
        @if ($match->distance_km !== null)
            <span class="absolute right-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-lamu-900 shadow-sm">
                {{ rtrim(rtrim(number_format((float) $match->distance_km, 3), '0'), '.') }} km
            </span>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4 sm:p-5">
        <h3 class="home-serif text-lg font-semibold leading-snug text-lamu-900">
            <a href="{{ $card['url'] }}" class="hover:underline">{{ $card['title'] }}</a>
        </h3>
        <p class="text-sm text-stone-600">{{ $card['location'] }}</p>
        <p class="home-serif text-lg font-semibold text-gold-600">
            @if ($card['price'] !== null)
                KES {{ number_format($card['price']) }}@if (filled($card['price_suffix'])) <span class="text-sm font-medium text-stone-600">{{ $card['price_suffix'] }}</span>@endif
            @else
                {{ $card['is_lease'] ? 'Rent on request' : 'Price on request' }}
            @endif
        </p>
        <p class="text-sm text-stone-700">
            {{ $acres }} {{ (float) $card['acres'] === 1.0 ? 'acre' : 'acres' }} available
            @if ($card['price_per_acre'])
                <span class="text-stone-400" aria-hidden="true">·</span>
                KES {{ number_format($card['price_per_acre']) }} / acre
            @endif
        </p>
        <p class="text-sm text-stone-600">{{ $reasons !== '' ? $reasons : 'Meets the recorded criteria.' }}</p>
        <a href="{{ $card['url'] }}" class="mt-auto inline-flex w-fit items-center pt-2 text-sm font-semibold text-lamu-800 hover:underline">
            View property details
            <span aria-hidden="true" class="ml-1">→</span>
        </a>
    </div>
</article>
