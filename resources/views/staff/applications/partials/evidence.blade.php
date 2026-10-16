@php
    $document = $profile['evidence'][$kind] ?? null;
    $documentLabel = ucfirst(str_replace('_', ' ', $kind));
    $previewUrl = route('account.applications.evidence', [$application, $kind, 'preview' => 1]);
@endphp
<article class="overflow-hidden rounded-xl border border-sand-200 bg-sand-50">
    <h4 class="p-3 text-sm font-semibold text-lamu-900">{{ $documentLabel }}</h4>
    @if ($document)
        @if (in_array($document['mime'] ?? '', ['image/jpeg', 'image/png'], true))
            <img src="{{ $previewUrl }}" alt="{{ $documentLabel }} submitted by applicant" loading="lazy" class="h-96 w-full bg-white object-contain">
        @elseif (($document['mime'] ?? '') === 'application/pdf')
            <iframe src="{{ $previewUrl }}" title="{{ $documentLabel }} preview" loading="lazy" class="h-96 w-full border-0 bg-white"></iframe>
        @else
            <p class="px-3 text-sm text-stone-600">Preview unavailable. Download the document to review it.</p>
        @endif
        <div class="flex flex-wrap gap-4 p-3 text-sm font-medium text-lamu-800">
            @if (in_array($document['mime'] ?? '', ['application/pdf', 'image/jpeg', 'image/png'], true))
                <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="underline">Open in new tab</a>
            @endif
            <a href="{{ route('account.applications.evidence', [$application, $kind]) }}" data-turbo="false" class="underline">Download {{ strtolower($documentLabel) }}</a>
        </div>
    @else
        <p class="px-3 pb-3 text-sm text-amber-900">Not uploaded. {{ $required ? 'Ask the applicant to provide this required evidence.' : 'Not required for this applicant type unless staff requests it.' }}</p>
    @endif
</article>
