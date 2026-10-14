<div class="mt-6 grid gap-4">
    @forelse ($records as $record)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-lamu-900"><a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.planning.show' : 'intelligence.planning.show', $record) }}">{{ $record->title }}</a></h2>
            <p class="mt-2 text-sm text-stone-600">{{ $record->area }} · {{ $record->instrument_status->label() }} @if ($record->effective_on) · effective {{ $record->effective_on->toFormattedDateString() }} @endif</p>
        </article>
    @empty
        <p class="text-stone-600">No planning record has been published.</p>
    @endforelse
</div>
<div class="mt-4">{{ $records->links() }}</div>
