<p class="mt-4 text-stone-700">{{ $record->area }} · {{ $record->instrument_status->label() }}</p>
<p class="mt-2 text-sm text-stone-600">Review status: {{ $record->publication_status->label() }}. Effective {{ $record->effective_on?->toFormattedDateString() ?: 'date not recorded' }}.</p>
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-3">
        <p><span class="font-medium">Permitted uses.</span> {{ $record->permitted_uses ?: 'Not recorded' }}</p>
        <p><span class="font-medium">Restrictions.</span> {{ $record->restrictions ?: 'Not recorded' }}</p>
        <p><span class="font-medium">Source.</span> {{ $record->source_document }}</p>
        <p><span class="font-medium">Map reference.</span> {{ $record->map_reference ?: 'Not recorded' }}</p>
    </div>
@else
    <p class="mt-6 text-stone-700">Permitted uses, restrictions, and the source document are part of an active Investor Pass.</p>
@endif
