<form method="GET" action="{{ url()->current() }}" class="mt-6 flex flex-wrap items-end gap-3">
    <div>
        <label for="plan-status" class="block text-sm font-medium">Status</label>
        <select id="plan-status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="plan-county" class="block text-sm font-medium">County</label>
        <select id="plan-county" name="county" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All counties</option>
            @foreach ($counties as $county)
                <option value="{{ $county->id }}" @selected((string) request('county') === (string) $county->id)>{{ $county->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="plan-locality" class="block text-sm font-medium">Location</label>
        <select id="plan-locality" name="locality" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">All locations</option>
            @foreach ($localities as $locality)
                <option value="{{ $locality->id }}" @selected((string) request('locality') === (string) $locality->id)>{{ $locality->name }}</option>
            @endforeach
        </select>
    </div>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Filter</button>
</form>

<div class="mt-6 grid gap-4">
    @forelse ($records as $record)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-lamu-900"><a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.planning.show' : 'intelligence.planning.show', $record) }}">{{ $record->title }}</a></h2>
            <p class="mt-2 text-sm text-stone-600">{{ $record->area }} · {{ $record->instrument_status->label() }} @if ($record->effective_on) · effective {{ $record->effective_on->toFormattedDateString() }} @endif</p>
            <p class="mt-1 text-sm text-stone-500">{{ $record->county?->name }} {{ $record->locality?->name }}</p>
        </article>
    @empty
        <p class="text-stone-600">No planning record has been published.</p>
    @endforelse
</div>
<div class="mt-4">{{ $records->links() }}</div>
