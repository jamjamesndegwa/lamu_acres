@php
    $requirementLabels = [
        'required' => 'Required to submit',
        'conditional' => 'Requested for this listing',
        'staff_to_determine' => 'Staff to determine',
        'staff_obtains' => 'Staff obtain',
        'at_completion' => 'At transaction stage',
        'optional' => 'Optional',
    ];
    $canReview = auth()->user()->hasPermission('staff.verification');
@endphp
<section id="seller-documents" class="mt-8 max-w-4xl">
    <h2 class="font-semibold text-lamu-900">Seller documents</h2>
    <p class="mt-1 text-sm text-stone-600">Private evidence. Uploading does not verify anything; record each review outcome with a reason.</p>
    <ul class="mt-3 space-y-2 text-sm">
        @foreach ($requirements as $requirement)
            <li class="flex flex-wrap justify-between gap-2 rounded-xl border border-sand-200 bg-white px-4 py-3">
                <span><span class="font-medium text-lamu-900">{{ $requirement['label'] }}</span> · {{ $requirementLabels[$requirement['requirement']] ?? $requirement['requirement'] }}<br><span class="text-stone-600">{{ $requirement['reason'] }}</span></span>
                <x-status-badge :label="$requirement['state']" />
            </li>
        @endforeach
    </ul>

    @if ($canReview && $property->ownershipDocuments->contains(fn ($document) => $document->hasFile() && $document->state !== \App\Enums\VerificationItemState::Verified))
        <form id="batch-document-approval-{{ $property->id }}" method="POST" action="{{ route('staff.ownership-documents.approve', $property) }}" data-turbo-frame="_top" class="mt-4 space-y-3 rounded-2xl border border-sand-200 bg-white p-4">
            @csrf
            @method('PUT')
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" data-select-document-reviews> Select all pending documents</label>
            <label class="block text-sm">Review reason for the selected documents
                <input name="reason" required minlength="8" maxlength="2000" value="{{ old('reason') }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Approve selected documents</button>
            @foreach (['document_ids', 'document_ids.*', 'reason'] as $field)
                @foreach ($errors->get($field) as $messages)
                    @foreach ((array) $messages as $message)
                        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
                    @endforeach
                @endforeach
            @endforeach
        </form>
    @endif

    @if ($canReview && ($showViewer ?? true))
        <x-private-file-viewer :documents="$property->ownershipDocuments" />
    @endif
    <ul class="mt-4 space-y-3">
        @forelse ($property->ownershipDocuments as $document)
            <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                @if ($canReview && $document->hasFile() && $document->state !== \App\Enums\VerificationItemState::Verified)
                    <label class="mb-2 flex items-center gap-2 text-sm">
                        <input type="checkbox" name="document_ids[]" value="{{ $document->id }}" form="batch-document-approval-{{ $property->id }}" @checked(in_array($document->id, old('document_ids', [])))>
                        Select {{ $document->document_type?->label() ?? 'document' }} for approval
                    </label>
                @endif
                <p class="font-medium text-lamu-900">{{ $document->document_type?->label() ?? 'Document' }} · {{ $document->state?->label() ?? 'Received' }}</p>
                <p class="text-stone-600">
                    @if ($document->hasFile())
                        {{ $document->original_name }} · uploaded {{ $document->created_at?->format('j M Y') }}
                        @if ($canReview)
                            · <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.ownership-documents.download', $document) }}">Open (logged)</a>
                        @endif
                    @else
                        No file. Recorded by staff.
                    @endif
                </p>
                @if ($document->review_note)
                    <p class="mt-1 text-stone-700">{{ $document->reviewer?->name ?? 'Staff' }}: {{ $document->review_note }}</p>
                @endif
                @if ($canReview && $document->hasFile())
                    <form method="POST" action="{{ route('staff.ownership-documents.review', $document) }}" data-turbo-frame="_top" class="mt-3 grid gap-2 sm:grid-cols-[auto_1fr_auto_auto] sm:items-end">
                        @csrf
                        @method('PUT')
                        <label class="text-sm">Outcome
                            <select name="state" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
                                @foreach ([\App\Enums\VerificationItemState::UnderReview, \App\Enums\VerificationItemState::Verified, \App\Enums\VerificationItemState::Failed, \App\Enums\VerificationItemState::Expired, \App\Enums\VerificationItemState::NotApplicable] as $state)
                                    <option value="{{ $state->value }}" @selected($document->state === $state)>{{ $state === \App\Enums\VerificationItemState::Failed ? 'Rejected' : $state->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">Reason
                            <input name="reason" required minlength="8" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
                        </label>
                        <label class="text-sm">Expires
                            <input type="date" name="expires_at" value="{{ $document->expires_at?->format('Y-m-d') }}" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
                        </label>
                        <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Record</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="text-sm text-stone-600">The seller has not uploaded any documents.</li>
        @endforelse
    </ul>

    @if ($canReview)
        <form method="POST" action="{{ route('staff.ownership-documents.not-applicable', $property) }}" data-turbo-frame="_top" class="mt-4 grid max-w-3xl gap-2 sm:grid-cols-[auto_1fr_auto] sm:items-end">
            @csrf
            <label class="text-sm">Document not applicable
                <select name="document_type" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
                    @foreach (\App\Enums\OwnershipDocumentType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Reason
                <input name="reason" required minlength="8" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Record exception</button>
        </form>
    @endif
</section>
