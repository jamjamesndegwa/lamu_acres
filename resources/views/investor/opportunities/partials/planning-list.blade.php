@php
    $countyContext ??= false;
@endphp

<section class="rounded-2xl border border-sand-200 bg-white p-5 sm:p-6">
    <h2 class="home-serif text-lg font-semibold text-lamu-900">{{ $heading }}</h2>
    @if ($countyContext)
        <p class="mt-1 text-sm text-stone-600">County-wide planning records. These do not confirm what applies to this parcel.</p>
        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-stone-500">County context</p>
    @else
        <p class="mt-1 text-sm text-stone-600">Released planning records linked to this locality.</p>
    @endif
    <div class="mt-4 max-w-3xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-stone-800">
        Planning information is provided for research purposes. Confirm current zoning, permitted uses and approvals with the responsible authority before purchasing or developing land.
    </div>
    @forelse ($records as $record)
        <article class="mt-4 border-t border-sand-100 pt-4">
            <h3 class="font-medium text-lamu-900"><a href="{{ route('investor.planning.show', $record) }}" class="hover:underline">{{ $record->title }}</a></h3>
            <p class="text-xs text-stone-500">{{ $record->instrument_status->label() }} · Effective {{ $record->effective_on?->toFormattedDateString() ?: 'date not recorded' }}</p>
            <p class="mt-1 text-sm text-stone-600"><span class="font-medium">Restrictions.</span> {{ $record->restrictions ?: 'Not recorded in the published extract.' }}</p>
            <p class="text-xs text-stone-500">Source: {{ $record->source_document }}</p>
        </article>
    @empty
        <p class="mt-3 text-sm text-stone-600">No released planning records in this group.</p>
    @endforelse
</section>
