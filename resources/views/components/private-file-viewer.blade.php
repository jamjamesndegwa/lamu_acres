@props(['documents' => collect(), 'evidence' => collect()])
@php
    $files = $documents->filter(fn ($document) => $document->hasFile())->map(fn ($document) => [
        'name' => $document->original_name,
        'label' => $document->document_type?->label() ?? 'Seller document',
        'mime' => $document->mime,
        'url' => route('staff.ownership-documents.download', $document),
        'preview' => route('staff.ownership-documents.download', [$document, 'inline' => 1]),
    ])->concat($evidence->map(fn ($file) => [
        'name' => $file->original_name,
        'label' => 'Verification evidence',
        'mime' => $file->mime,
        'url' => route('staff.verification.files.preview', $file),
        'preview' => route('staff.verification.files.preview', [$file, 'inline' => 1]),
    ]));
@endphp
<section data-private-file-viewer class="mt-4 overflow-hidden rounded-2xl border border-sand-200 bg-white">
    <div class="border-b border-sand-200 p-4">
        <h2 class="font-semibold text-lamu-900">Document viewing section</h2>
        <p class="mt-1 text-sm text-stone-600">Select a file to inspect it here while recording your decision. Images and PDFs open in this viewer. Other files can be downloaded. Access is private and logged.</p>
        <div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
            @forelse ($files as $file)
                <button type="button" data-preview-file data-preview-url="{{ $file['preview'] }}" data-download-url="{{ $file['url'] }}" data-file-mime="{{ $file['mime'] }}" data-file-name="{{ $file['name'] }}" class="block w-full rounded-lg border border-sand-200 p-3 text-left text-sm hover:bg-stone-50" aria-pressed="false">
                    <span class="block font-medium text-lamu-900">{{ $file['label'] }}</span>
                    <span class="block break-all text-stone-600">{{ $file['name'] }}</span>
                </button>
            @empty
                <p class="text-sm text-stone-600">No files have been provided yet.</p>
            @endforelse
        </div>
    </div>
    <div class="p-4">
        <p data-preview-name role="status" aria-live="polite" class="mb-3 break-all text-sm font-medium text-lamu-900">Choose a document above to view it.</p>
        <div class="rounded-xl bg-stone-100 p-2">
            <img data-preview-image hidden alt="" class="max-h-[70vh] w-full object-contain">
            <iframe data-preview-pdf hidden title="Private document preview" class="h-[70vh] min-h-96 w-full rounded-lg bg-white" referrerpolicy="no-referrer"></iframe>
            <p data-preview-message class="p-6 text-center text-sm text-stone-600">Your selected file will appear here.</p>
        </div>
        <a data-preview-download hidden target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-medium text-lamu-800 underline">Open or download the original file</a>
    </div>
</section>
