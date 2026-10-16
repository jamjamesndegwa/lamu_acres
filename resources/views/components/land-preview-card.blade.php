@props(['listing', 'layout' => 'grid'])

@php
    $acres = rtrim(rtrim(number_format($listing['acres'], 2), '0'), '.');
    $perAcre = array_key_exists('price_per_acre', $listing)
        ? $listing['price_per_acre']
        : ($listing['acres'] > 0 && $listing['price'] !== null ? $listing['price'] / $listing['acres'] : null);
    $pricePerAcre = $perAcre !== null ? 'KES '.number_format($perAcre).' /acre' : null;
    $isList = $layout === 'list';
@endphp

<article {{ $attributes->class([
    'flex overflow-hidden rounded-md border border-sand-200 bg-white shadow-sm shadow-stone-900/5',
    'flex-col sm:flex-row' => $isList,
    'h-full flex-col' => ! $isList,
]) }}>
    <div @class(['shrink-0 overflow-hidden bg-sand-50', 'h-44 sm:h-auto sm:w-44' => $isList, 'h-44 sm:h-32 xl:h-36' => ! $isList])>
        <img src="{{ asset($listing['image']) }}" alt="" loading="lazy" class="h-full w-full object-cover">
    </div>
    <div class="flex flex-1 flex-col gap-0.5 px-3 py-2.5">
        <h3 class="home-serif text-base font-semibold leading-snug text-stone-900">{{ $listing['title'] }}</h3>
        <p class="flex items-center gap-1 text-xs text-stone-600">
            <svg class="h-3.5 w-3.5 shrink-0 text-lamu-700" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18s6-5.33 6-10a6 6 0 1 0-12 0c0 4.67 6 10 6 10Zm0-7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd" />
            </svg>
            <span><span class="sr-only">Location: </span>{{ $listing['location'] }}</span>
        </p>
        @isset($listing['distance_km'])
            <p class="mt-1 text-xs font-medium text-lamu-800">Approx. {{ number_format($listing['distance_km'], 1) }} km from search point</p>
        @endisset
        @isset($listing['type'])<p class="text-xs text-stone-600">{{ $listing['type'] }}</p>@endisset
        @isset($listing['verification'])<p class="text-xs text-stone-600">{{ $listing['verification'] }}</p>@endisset
        <p class="home-serif mt-0.5 text-lg font-semibold leading-tight text-gold-500">
            @if ($listing['price'] !== null)
                {{ $listing['currency'] ?? 'KES' }} {{ number_format($listing['price']) }}@if (filled($listing['price_suffix'] ?? null)) <span class="text-sm font-medium">{{ $listing['price_suffix'] }}</span>@endif
            @else
                {{ ($listing['is_lease'] ?? false) ? 'Rent on request' : 'Price on request' }}
            @endif
        </p>
        @isset($listing['price_basis_label'])<p class="text-xs text-stone-500">{{ $listing['price_basis_label'] }} · {{ $listing['is_lease'] ? 'For lease' : 'For sale' }}</p>@endisset
        @isset($listing['quoted_rate'])<p class="text-xs text-stone-600">Quoted rate: {{ $listing['quoted_rate'] }}</p>@endisset
        @if ($listing['is_lease'] ?? false)
            <p class="text-xs font-medium text-lamu-800">For lease</p>
        @endif
        <div class="flex flex-wrap items-center gap-x-1.5 text-xs text-stone-700">
            <span>{{ $acres }} {{ $listing['acres'] == 1 ? 'acre' : 'acres' }} available</span>
            @if ($pricePerAcre)
                <span class="text-stone-400" aria-hidden="true">·</span>
                <span>{{ $pricePerAcre }}</span>
            @endif
        </div>
        @isset($listing['url'])
            <a href="{{ $listing['url'] }}" class="mt-1 inline-flex w-fit items-center self-end text-xs font-semibold text-lamu-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">View Details <span aria-hidden="true" class="ml-1">→</span></a>
        @endisset
        @if ($listing['save_url'] ?? null)
            <form action="{{ $listing['save_url'] }}" method="POST" class="mt-2">
                @csrf
                <button type="submit" class="min-h-11 rounded-full border border-sand-200 px-4 py-2 text-xs font-semibold text-lamu-800">Save property</button>
            </form>
        @elseif (array_key_exists('save_url', $listing) && auth()->guest())
            <a href="{{ $listing['url'] }}" class="mt-2 min-h-11 content-center text-xs font-medium text-lamu-800 underline">Sign in to save from property details</a>
        @endif
    </div>
</article>
