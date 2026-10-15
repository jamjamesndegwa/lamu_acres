@php
    $touch ??= false;
    $rowClass = $touch ? 'min-h-11 text-sm' : 'min-h-7 text-xs';
@endphp

<nav class="{{ $navClass ?? '' }}" aria-labelledby="{{ $headingId }}">
    <h2 id="{{ $headingId }}" @class(['home-serif font-semibold text-stone-900', 'text-lg' => $touch, 'text-base' => ! $touch])>Locations</h2>
    <a @class(['mt-1 flex items-center hover:underline', $rowClass, 'font-semibold text-lamu-900' => $county === null, 'text-lamu-800' => $county !== null]) href="{{ route('land.index') }}" @if ($county === null) aria-current="page" @endif>All locations</a>
    @forelse ($counties as $place)
        <ul class="border-t border-sand-200 pt-0.5">
            @foreach ([[$place, null], ...$place->localities->map(fn ($area) => [$place, $area])] as [$placeCounty, $area])
                @php
                    $isCurrent = $area ? $locality?->is($area) : ($county?->is($placeCounty) && $locality === null);
                    $count = ($area ?? $placeCounty)->available_listings_count;
                @endphp
                <li>
                    <a @class([
                        'flex items-center gap-2 rounded-sm hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800',
                        $rowClass,
                        'pl-4' => $area !== null,
                        'font-semibold text-lamu-900' => $isCurrent,
                        'text-stone-700' => ! $isCurrent,
                    ]) href="{{ $area ? route('land.index', [$placeCounty, $area]) : route('land.index', $placeCounty) }}" @if ($isCurrent) aria-current="page" @endif>
                        <svg class="h-3.5 w-3.5 shrink-0 text-lamu-800" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18s6-5.33 6-10a6 6 0 1 0-12 0c0 4.67 6 10 6 10Zm0-7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd" /></svg>
                        <span class="flex-1">{{ $area?->name ?? $placeCounty->name }}</span>
                        <span class="text-xs text-stone-500" title="Published listings">{{ $count }} <span class="sr-only">published {{ \Illuminate\Support\Str::plural('listing', $count) }}</span></span>
                    </a>
                </li>
            @endforeach
        </ul>
    @empty
        <p class="mt-3 text-sm text-stone-600">No locations are recorded yet.</p>
    @endforelse
</nav>
