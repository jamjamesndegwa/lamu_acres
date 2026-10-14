<x-filter-bar :action="url()->current()" class="mt-6 flex flex-wrap items-end gap-3">
    <div>
        <label for="imagery-county" class="block text-sm font-medium">County</label>
        <select id="imagery-county" name="county" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) request('county') === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="imagery-locality" class="block text-sm font-medium">Location</label>
        <select id="imagery-locality" name="locality" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Filter</button>
</x-filter-bar>

<div class="mt-6 grid gap-4">
    @forelse ($comparisons as $comparison)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.imagery.show' : 'intelligence.imagery.show', $comparison) }}">{{ $comparison->area_label }}</a>
            </h2>
            <p class="mt-2 text-sm text-stone-600">Before {{ $comparison->beforeScene?->acquired_on?->toFormattedDateString() ?: 'date not recorded' }} · After {{ $comparison->afterScene?->acquired_on?->toFormattedDateString() ?: 'date not recorded' }}</p>
            <p class="mt-1 text-sm text-stone-500">{{ $comparison->beforeScene?->county?->name ?: $comparison->afterScene?->county?->name }} {{ $comparison->beforeScene?->locality?->name ?: $comparison->afterScene?->locality?->name }}</p>
        </article>
    @empty
        <p class="text-stone-600">No imagery comparison has been published. Nothing here is an automatic construction reading.</p>
    @endforelse
</div>
<div class="mt-4">{{ $comparisons->links() }}</div>
