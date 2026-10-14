<x-workspace-layout :heading="$record->title">
    <p class="mt-3 text-sm text-stone-600">{{ $record->reference }} · {{ $record->instrument_status->label() }} · {{ $record->publication_status->label() }} · {{ $record->area }}</p>
    <p class="mt-3">Source: {{ $record->source_document }}</p>
    <p class="mt-2">Restrictions: {{ $record->restrictions }}</p>
    <div class="mt-4 flex gap-2">
        @if (in_array($record->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
            <form method="POST" action="{{ route('staff.planning.submit', $record) }}">@csrf <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Submit</button></form>
        @endif
        @if ($record->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
            <form method="POST" action="{{ route('staff.planning.publish', $record) }}">@csrf <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Publish</button></form>
        @endif
    </div>
</x-workspace-layout>
