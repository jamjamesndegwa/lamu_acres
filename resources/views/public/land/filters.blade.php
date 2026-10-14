<form method="GET" action="{{ $directoryUrl }}" class="space-y-4 text-sm">
    <input type="hidden" name="view" value="{{ $filters['view'] }}">

    <label class="block">
        <span class="font-medium text-stone-700">Search</span>
        <input name="q" value="{{ $filters['q'] }}" type="search" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
    </label>

    <label class="block">
        <span class="font-medium text-stone-700">Purpose</span>
        <select name="purpose" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
            <option value="">Any</option>
            @foreach (\App\Enums\ListingPurpose::cases() as $purpose)
                <option value="{{ $purpose->value }}" @selected($filters['purpose'] === $purpose)>{{ $purpose->label() }}</option>
            @endforeach
        </select>
    </label>

    <label class="block">
        <span class="font-medium text-stone-700">Property type</span>
        <select name="type" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
            <option value="">Any</option>
            @foreach (\App\Enums\PropertyType::cases() as $type)
                <option value="{{ $type->value }}" @selected($filters['type'] === $type)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </label>

    <div class="grid grid-cols-2 gap-3">
        <label class="block">
            <span class="font-medium text-stone-700">Min price</span>
            <input name="min_price" value="{{ $filters['min_price'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="block">
            <span class="font-medium text-stone-700">Max price</span>
            <input name="max_price" value="{{ $filters['max_price'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="block">
            <span class="font-medium text-stone-700">Min per acre</span>
            <input name="min_price_per_acre" value="{{ $filters['min_price_per_acre'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="block">
            <span class="font-medium text-stone-700">Max per acre</span>
            <input name="max_price_per_acre" value="{{ $filters['max_price_per_acre'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="block">
            <span class="font-medium text-stone-700">Min acres</span>
            <input name="min_acres" value="{{ $filters['min_acres'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
        <label class="block">
            <span class="font-medium text-stone-700">Max acres</span>
            <input name="max_acres" value="{{ $filters['max_acres'] }}" inputmode="decimal" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
        </label>
    </div>

    <label class="block">
        <span class="font-medium text-stone-700">Sort</span>
        <select name="sort" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
            <option value="newest" @selected($filters['sort'] === 'newest')>Newest</option>
            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>Price, low to high</option>
            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>Price, high to low</option>
            <option value="acres_asc" @selected($filters['sort'] === 'acres_asc')>Acreage, low to high</option>
            <option value="acres_desc" @selected($filters['sort'] === 'acres_desc')>Acreage, high to low</option>
        </select>
    </label>

    <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-2 font-medium text-white">Apply filters</button>
</form>
