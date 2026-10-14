<p class="mt-4 text-stone-700">{{ $record->area }} · {{ $record->instrument_status->label() }}</p>
<p class="mt-2 text-sm text-stone-600">Review status: {{ $record->publication_status->label() }}. Effective {{ $record->effective_on?->toFormattedDateString() ?: 'date not recorded' }}.</p>
<p class="mt-2 text-sm text-stone-600">Applicable area: {{ $record->area }}@if ($record->county) · {{ $record->county->name }}@endif @if ($record->locality) · {{ $record->locality->name }}@endif.</p>
@if ($premium)
    <div class="mt-6 max-w-3xl space-y-3">
        <p><span class="font-medium">Permitted uses.</span> {{ $record->permitted_uses ?: 'Not recorded' }}</p>
        <p><span class="font-medium">Restrictions.</span> {{ $record->restrictions ?: 'Not recorded' }}</p>
        <p><span class="font-medium">Official reference.</span> {{ $record->source_document }}</p>
        @if ($record->source_url)
            <p><a class="text-lamu-800 hover:underline" href="{{ $record->source_url }}">{{ $record->source_url }}</a></p>
        @endif
        <p><span class="font-medium">Source.</span> {{ $record->source_document }}</p>
        <p><span class="font-medium">Map reference.</span> {{ $record->map_reference ?: 'Not recorded' }}</p>
        <p><span class="font-medium">Last review.</span> {{ $record->published_at?->toFormattedDateString() ?: 'Not recorded' }}</p>
        <h2 class="font-semibold text-lamu-900">Licensed files</h2>
        <ul class="text-sm">
            @forelse ($record->files as $file)
                <li><a class="text-lamu-800 hover:underline" data-turbo="false" href="{{ route('intelligence.files.show', $file) }}">{{ $file->original_name }}</a></li>
            @empty
                <li>No file has been attached.</li>
            @endforelse
        </ul>
    </div>
@else
    <p class="mt-6 text-stone-700">Permitted uses, restrictions, and the source document are part of an active Investor Pass.</p>
@endif
