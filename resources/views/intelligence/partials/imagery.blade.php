<x-filter-bar :action="url()->current()" class="mt-4 grid gap-3 rounded-lg border border-sand-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label for="imagery-county" class="block text-xs font-medium text-stone-700">County</label>
        <select id="imagery-county" name="county" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
            <option value="">All counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) request('county') === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="imagery-locality" class="block text-xs font-medium text-stone-700">Location</label>
        <select id="imagery-locality" name="locality" class="mt-1 min-h-10 w-full rounded-md border border-sand-200 bg-white px-3 py-2 text-sm">
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
    <h2 class="home-serif text-lg font-semibold text-lamu-900">Published imagery comparisons</h2>
    <p class="text-xs text-stone-600">{{ $comparisons->total() }} comparisons matching your selection</p>
</div>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($comparisons as $comparison)
        <article class="flex flex-col rounded-lg border border-sand-200 bg-white p-4">
            <h3 class="home-serif text-base font-semibold leading-snug text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.imagery.show' : 'intelligence.imagery.show', $comparison) }}">{{ $comparison->area_label }}</a>
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-stone-600">Before {{ $comparison->beforeScene?->acquired_on?->toFormattedDateString() ?: 'date not recorded' }} · After {{ $comparison->afterScene?->acquired_on?->toFormattedDateString() ?: 'date not recorded' }}</p>
            <p class="mt-2 text-xs text-stone-500">{{ $comparison->beforeScene?->license_name ?: 'Licence not recorded' }}</p>
            <p class="mt-2 line-clamp-2 text-xs text-stone-600">{{ \Illuminate\Support\Str::limit($comparison->observed_change ?: 'Documented changes are on the comparison page.', 120) }}</p>
            <a href="{{ route($frame === 'workspace' ? 'investor.imagery.show' : 'intelligence.imagery.show', $comparison) }}" class="mt-3 inline-flex min-h-9 items-center self-start text-xs font-semibold text-lamu-800 hover:underline">View comparison →</a>
        </article>
    @empty
        <p class="rounded-lg border border-sand-200 bg-white p-5 text-sm text-stone-600 sm:col-span-2 lg:col-span-3">No imagery comparison has been published. Nothing here is an automatic construction reading.</p>
    @endforelse
</div>
<div class="mt-4">{{ $comparisons->links() }}</div>
