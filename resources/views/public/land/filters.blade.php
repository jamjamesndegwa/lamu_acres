@php
    $showHeading ??= false;
    $inputClass = 'h-8 w-full rounded-md border border-sand-200 bg-white px-2.5 text-xs text-stone-900 placeholder:text-stone-400 focus:border-lamu-700 focus:outline-none focus:ring-2 focus:ring-lamu-100';
    $labelClass = 'mb-1 block text-xs font-medium text-stone-800';
    $hasPricePerAcre = $filters['min_price_per_acre'] !== null || $filters['max_price_per_acre'] !== null;
    $ranges = [
        ['key' => 'price', 'legend' => 'Price range (KES)', 'unit' => 'price in KES', 'mode' => 'numeric'],
        ['key' => 'acres', 'legend' => 'Acres', 'unit' => 'acreage', 'mode' => 'decimal'],
    ];
@endphp

<div class="flex items-center justify-between gap-3">
    @if ($showHeading)
        <h2 class="home-serif text-lg font-semibold text-stone-900">Filters</h2>
    @endif
    <a href="{{ $clearFiltersUrl }}" class="ml-auto rounded-sm text-xs font-medium text-lamu-800 underline underline-offset-2 hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Reset</a>
</div>

<x-filter-bar :action="$directoryUrl" class="mt-2 space-y-2.5 text-xs">
    @if ($filters['view'] !== 'cards')
        <input type="hidden" name="view" value="{{ $filters['view'] }}">
    @endif
    @if ($filters['sort'] !== 'newest')
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
    @endif

    <label class="block">
        <span class="{{ $labelClass }}">Location</span>
        <input name="q" value="{{ $filters['q'] }}" type="search" maxlength="100" placeholder="Search location or area…" class="{{ $inputClass }}">
    </label>

    <fieldset>
        <legend class="sr-only">Purpose</legend>
        <div class="grid grid-cols-3 overflow-hidden rounded-md border border-sand-200 bg-white">
            @foreach (['' => 'Any', 'sale' => 'Buy', 'lease' => 'Lease'] as $value => $label)
                <label class="relative border-sand-200 [&:not(:first-child)]:border-l">
                    <input type="radio" name="purpose" value="{{ $value }}" class="peer sr-only" @checked(($filters['purpose']?->value ?? '') === $value)>
                    <span class="flex h-8 cursor-pointer items-center justify-center text-xs text-stone-700 peer-checked:bg-lamu-800 peer-checked:font-medium peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-gold-500">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <label class="block">
        <span class="{{ $labelClass }}">Property type</span>
        <select name="type" class="{{ $inputClass }}">
            <option value="">Any</option>
            @foreach (\App\Enums\PropertyType::cases() as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </label>

    @foreach ($ranges as $range)
        <fieldset>
            <legend class="{{ $labelClass }}">{{ $range['legend'] }}</legend>
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                <label class="block">
                    <span class="sr-only">Minimum {{ $range['unit'] }}</span>
                    <input name="min_{{ $range['key'] }}" value="{{ $filters['min_'.$range['key']] }}" inputmode="{{ $range['mode'] }}" placeholder="Min" class="{{ $inputClass }}">
                </label>
                <span class="text-stone-400" aria-hidden="true">—</span>
                <label class="block">
                    <span class="sr-only">Maximum {{ $range['unit'] }}</span>
                    <input name="max_{{ $range['key'] }}" value="{{ $filters['max_'.$range['key']] }}" inputmode="{{ $range['mode'] }}" placeholder="Max" class="{{ $inputClass }}">
                </label>
            </div>
        </fieldset>
    @endforeach

    <details class="group rounded-md bg-sand-50" @if ($hasPricePerAcre) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between rounded-md px-2.5 py-2 text-xs font-medium text-stone-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-lamu-800 [&::-webkit-details-marker]:hidden">
            Price per acre
            <svg class="h-4 w-4 text-stone-500 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
        </summary>
        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 px-2.5 pb-2.5">
            <label class="block">
                <span class="sr-only">Minimum price per acre in KES</span>
                <input name="min_price_per_acre" value="{{ $filters['min_price_per_acre'] }}" inputmode="numeric" placeholder="Min KES" class="{{ $inputClass }}">
            </label>
            <span class="text-stone-400" aria-hidden="true">—</span>
            <label class="block">
                <span class="sr-only">Maximum price per acre in KES</span>
                <input name="max_price_per_acre" value="{{ $filters['max_price_per_acre'] }}" inputmode="numeric" placeholder="Max KES" class="{{ $inputClass }}">
            </label>
        </div>
    </details>

    <button type="submit" class="h-9 w-full rounded-md bg-lamu-800 px-3 text-xs font-semibold text-white hover:bg-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Apply filters</button>
</x-filter-bar>
