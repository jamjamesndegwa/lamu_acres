@php
    use App\Enums\PropertyStatus;

    $openRequests = $property->informationRequests->whereNull('responded_at');
    $requirementLabels = [
        'required' => 'Required before you submit',
        'conditional' => 'Requested for this listing',
        'staff_to_determine' => 'Staff will tell you if needed',
        'staff_obtains' => 'Staff obtain this',
        'at_completion' => 'Needed at transaction stage',
        'optional' => 'Optional',
    ];
    $banner = match (true) {
        $openRequests->isNotEmpty() => ['Staff need more information', 'Reply below and update the listing. Your listing stays with staff until they decide.'],
        $property->status === PropertyStatus::Draft => ['Draft, not yet submitted', 'Complete the checklist at the bottom of this page, then submit for staff review.'],
        $property->status === PropertyStatus::Review => ['Submitted, awaiting ownership verification', 'Staff are checking your documents and authority. Nothing is published until they approve it.'],
        $property->status === PropertyStatus::Published => ['Published', 'Buyers can see the public details. Private documents and exact location stay hidden.'],
        $property->status === PropertyStatus::Reserved => ['Reserved', 'A buyer is in an active process. Staff manage the next steps.'],
        $property->status === PropertyStatus::Leased => ['Leased', 'Staff recorded a confirmed lease. The listing is not advertised.'],
        default => [$property->status->label(), 'This listing is not currently advertised.'],
    };
@endphp

<x-workspace-layout :heading="$property->title">
    <p class="mt-3 text-sm text-stone-600">{{ $property->reference }} · {{ $property->purpose?->label() ?? 'Purpose not set' }} · {{ $verified ? 'Relationship verified' : 'Relationship awaiting verification' }}</p>

    <section class="mt-4 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5" aria-live="polite">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-lamu-900">{{ $banner[0] }}</p>
                <p class="mt-1 text-sm text-stone-700">{{ $banner[1] }}</p>
            </div>
            <x-status-badge :label="$property->status->label()" />
        </div>
        @if ($canEdit)
            <a href="{{ route('seller.properties.edit', $property) }}" class="mt-4 inline-block rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Edit listing details</a>
        @endif
    </section>

    @if ($openRequests->isNotEmpty() && $isSubmitter)
        <section id="requests" class="mt-6 max-w-3xl space-y-3">
            <h2 class="font-semibold text-lamu-900">Requests from staff</h2>
            @foreach ($openRequests as $item)
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="text-stone-800">{{ $item->question }}</p>
                    <form method="POST" action="{{ route('seller.properties.requests.respond', [$property, $item]) }}" data-turbo-frame="_top" data-confirm="Send this reply to staff?" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="response" rows="3" required maxlength="2000" class="w-full rounded-lg border border-sand-200 px-3 py-2" aria-label="Your reply"></textarea>
                        <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send reply</button>
                    </form>
                </article>
            @endforeach
        </section>
    @endif

    <section class="mt-6 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Listing summary</h2>
        <dl class="mt-3 grid gap-4 rounded-2xl border border-sand-200 bg-white p-5 text-sm sm:grid-cols-2">
            <div><dt class="text-stone-500">{{ $property->isLease() ? 'Asking rent' : 'Asking price' }}</dt><dd>{{ $property->formattedPrice() ?? 'Not set' }}@if ($property->price_negotiable) · negotiable @endif</dd></div>
            <div><dt class="text-stone-500">Location</dt><dd>{{ $property->locality_id ? $property->locationLabel() : 'Not set' }}</dd></div>
            <div><dt class="text-stone-500">Parcel size</dt><dd>{{ $property->acreage ? number_format((float) $property->acreage, 2).' acres' : 'Not set' }}@if ($property->isPartialOffer()) · {{ number_format((float) $property->offered_acreage, 2) }} acres offered @endif</dd></div>
            <div><dt class="text-stone-500">Tenure</dt><dd>{{ $property->tenure?->label() ?? 'Not set' }}</dd></div>
            @if ($property->isLease())
                <div><dt class="text-stone-500">Lease term</dt><dd>{{ $property->lease_min_months ? $property->lease_min_months.' months minimum' : 'Not set' }}@if ($property->lease_max_months) · up to {{ $property->lease_max_months }} months @endif</dd></div>
            @endif
            <div><dt class="text-stone-500">Available from</dt><dd>{{ $property->available_from?->format('j M Y') ?? 'Not stated' }}</dd></div>
        </dl>
    </section>

    <section id="photos" class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Photographs</h2>
        <p class="mt-1 text-sm text-stone-600">Location data is removed from the copy shown to buyers. The first photo, or the one marked as cover, leads the listing.</p>
        @if ($photos->isNotEmpty())
            <ul class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach ($photos as $photo)
                    <li class="rounded-xl border border-sand-200 bg-white p-2 text-sm">
                        <img src="{{ route('seller.properties.photos.preview', [$property, $photo]) }}" alt="Photo {{ $loop->iteration }} of {{ $property->title }}" class="aspect-[4/3] w-full rounded-lg object-cover">
                        <p class="mt-2 truncate text-stone-700">{{ $photo->original_name }}@if ($photo->is_cover) · <span class="font-medium">Cover</span>@endif</p>
                        @can('manageMedia', $property)
                            <div class="mt-1 flex flex-wrap gap-3">
                                @unless ($photo->is_cover)
                                    <form method="POST" action="{{ route('seller.properties.photos.cover', [$property, $photo]) }}" data-turbo-frame="_top">
                                        @csrf
                                        @method('PUT')
                                        <button class="font-medium text-lamu-800">Make cover</button>
                                    </form>
                                @endunless
                                @can('delete', $photo)
                                    <form method="POST" action="{{ route('seller.properties.photos.destroy', [$property, $photo]) }}" data-turbo-frame="_top" data-confirm="Remove this photo?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-700">Remove</button>
                                    </form>
                                @endcan
                            </div>
                        @endcan
                    </li>
                @endforeach
            </ul>
            @can('manageMedia', $property)
                @if ($photos->count() > 1)
                    <form method="POST" action="{{ route('seller.properties.photos.order', $property) }}" data-turbo-frame="_top" class="mt-4 space-y-2">
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-medium">Photo order</p>
                        @foreach ($photos as $photo)
                            <label class="flex items-center gap-3 text-sm">
                                <span class="w-48 truncate">{{ $photo->original_name }}</span>
                                <input type="number" min="1" max="1000" name="positions[{{ $photo->id }}]" value="{{ $photo->position }}" class="w-24 rounded-lg border border-sand-200 px-2 py-1">
                            </label>
                        @endforeach
                        <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Save order</button>
                    </form>
                @endif
            @endcan
        @else
            <p class="mt-3 text-sm text-stone-600">No photographs yet. At least one is needed before you submit.</p>
        @endif
        @can('manageMedia', $property)
            <form method="POST" action="{{ route('seller.properties.photos.store', $property) }}" enctype="multipart/form-data" data-turbo-frame="_top" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <x-upload-manager name="photo" label="Add a photograph (JPEG, PNG or WebP)" accept="image/jpeg,image/png,image/webp" required />
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Upload photo</button>
            </form>
            @error('photo')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        @endcan
    </section>

    <section id="documents" class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Private ownership documents</h2>
        <p class="mt-1 text-sm text-stone-600">Only you and verification staff can open these. They are never shown on the public listing. Uploading a document does not mean it has been accepted.</p>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($requirements as $requirement)
                <li class="flex flex-wrap justify-between gap-2 rounded-xl border border-sand-200 bg-white px-4 py-3">
                    <span><span class="font-medium text-lamu-900">{{ $requirement['label'] }}</span> · {{ $requirementLabels[$requirement['requirement']] ?? $requirement['requirement'] }}<br><span class="text-stone-600">{{ $requirement['reason'] }}</span></span>
                    <x-status-badge :label="$requirement['state']" />
                </li>
            @endforeach
        </ul>

        @if ($property->ownershipDocuments->isNotEmpty())
            <h3 class="mt-5 text-sm font-medium text-lamu-900">Uploaded</h3>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($property->ownershipDocuments as $document)
                    <li class="rounded-xl border border-sand-200 bg-white px-4 py-3">
                        <span class="font-medium">{{ $document->document_type?->label() ?? 'Document' }}</span> · {{ $document->state?->label() ?? 'Received' }}
                        @if ($document->hasFile() && $document->user_id === auth()->id())
                            · <a class="text-lamu-800 hover:underline" href="{{ route('seller.documents.download', $document) }}">{{ $document->original_name }}</a>
                        @endif
                        @if ($document->review_note)
                            <p class="mt-1 text-stone-700">Staff note: {{ $document->review_note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @unless (in_array($property->status, [PropertyStatus::Sold, PropertyStatus::Archived], true))
            <form method="POST" action="{{ route('seller.properties.documents.store', $property) }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Store this document privately for staff review?" class="mt-4 space-y-3 rounded-2xl border border-sand-200 bg-white p-4">
                @csrf
                <label class="block text-sm">
                    <span class="font-medium">Document type</span>
                    <select name="document_type" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        @foreach (\App\Enums\OwnershipDocumentType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('document_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <x-upload-manager name="document" label="File (PDF, JPG or PNG, up to 10 MB)" accept=".pdf,image/jpeg,image/png" required />
                @foreach (['document_type', 'document'] as $field)
                    @error($field)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Upload privately</button>
            </form>
        @endunless
    </section>

    <section id="verification" class="mt-8 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Verification summary</h2>
        <p class="mt-1 text-sm text-stone-700">{{ $passport['level'] }}</p>
        <x-document-checklist
            empty="No verification summary has been recorded."
            :items="collect($passport['items'])->map(fn ($item) => ['label' => $item['category'], 'state' => $item['outcome']])->all()"
        />
    </section>

    @if ($verified)
        <section id="authority" class="mt-8 max-w-3xl">
            <h2 class="font-semibold text-lamu-900">Marketing authority</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($property->authorities as $authority)
                    <li>{{ $authority->status->label() }} · {{ $authority->scope?->label() ?? 'Sale and lease' }}@if ($authority->valid_until) · valid until {{ $authority->valid_until->format('j M Y') }}@endif</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('seller.properties.authority', $property) }}" data-turbo-frame="_top" data-confirm="Submit this marketing authority for staff review?" class="mt-3 space-y-3 rounded-2xl border border-sand-200 bg-white p-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium">Authority covers</span>
                        <select name="scope" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                            @foreach (\App\Enums\AuthorityScope::cases() as $scope)
                                <option value="{{ $scope->value }}" @selected(old('scope', $property->purpose?->value) === $scope->value)>{{ $scope->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium">Valid until (optional)</span>
                        <input type="date" name="valid_until" value="{{ old('valid_until') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="font-medium">Authority statement</span>
                    <textarea name="terms" rows="3" required minlength="20" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="I authorise Lamu Acres to market this land...">{{ old('terms') }}</textarea>
                </label>
                @foreach (['scope', 'valid_until', 'terms'] as $field)
                    @error($field)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Submit authority</button>
            </form>
        </section>

        <section id="instructions" class="mt-8 max-w-3xl">
            <h2 class="font-semibold text-lamu-900">Ask staff to change this listing</h2>
            <p class="mt-1 text-sm text-stone-600">Price, availability and withdrawal changes are applied by staff after review. Sales and leases are only recorded by staff once confirmed.</p>
            <form method="POST" action="{{ route('seller.properties.instructions', $property) }}" data-turbo-frame="_top" data-confirm="Send this request to staff?" class="mt-3 space-y-3 rounded-2xl border border-sand-200 bg-white p-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium">Request type</span>
                        <select name="kind" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                            @foreach (\App\Enums\SaleInstructionKind::cases() as $kind)
                                @continue($kind === \App\Enums\SaleInstructionKind::OfferResponse)
                                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium">Availability change</span>
                        <select name="requested_availability" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                            <option value="">No availability change</option>
                            <option value="available">Make available</option>
                            <option value="unavailable">Make unavailable</option>
                        </select>
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="font-medium">Details</span>
                    <textarea name="body" rows="3" required minlength="5" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('body') }}</textarea>
                </label>
                @foreach (['kind', 'body', 'requested_availability'] as $field)
                    @error($field)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                @endforeach
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send request</button>
            </form>
            @if ($property->saleInstructions->isNotEmpty())
                <h3 class="mt-5 text-sm font-medium text-lamu-900">Request history</h3>
                <ol class="mt-2 space-y-2 text-sm">
                    @foreach ($property->saleInstructions as $instruction)
                        <li>{{ $instruction->created_at?->format('j M Y') }} · {{ $instruction->kind->label() }}: {{ $instruction->body }}</li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif

    @if ($isSubmitter && $property->status === PropertyStatus::Draft)
        <section id="submit" class="mt-10 max-w-3xl rounded-2xl border border-lamu-900 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Review and submit</h2>
            @if ($gaps === [])
                <p class="mt-2 text-sm text-stone-700">Everything needed for review is in place. Staff will verify ownership, documents and authority before publishing. Submitting does not publish the listing.</p>
                <form method="POST" action="{{ route('seller.properties.submission', $property) }}" data-turbo-frame="_top" data-confirm="Submit this listing to staff for review?" class="mt-4">
                    @csrf
                    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit for review</button>
                </form>
            @else
                <p class="mt-2 text-sm text-stone-700">Complete these items before you can submit:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-stone-700">
                    @foreach ($gaps as $gap)
                        <li>{{ $gap }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
    @error('listing')<p class="mt-4 max-w-3xl text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
</x-workspace-layout>
