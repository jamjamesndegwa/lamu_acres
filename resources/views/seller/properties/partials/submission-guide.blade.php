@php
    $dataGaps = collect($submissionGaps)->reject(fn ($message, $key) => $key === 'photos' || str_starts_with($key, 'documents.'));
    $requiredDocuments = collect($requirements)->whereIn('requirement', ['required', 'conditional']);
@endphp
<section class="mt-6 rounded-2xl border border-sand-200 bg-white p-5">
    <h2 class="text-lg font-semibold text-lamu-900">Everything to provide for your listing</h2>
    <p class="mt-2 text-sm text-stone-600">Complete the details, select your photographs and choose a file beside each document type. Then use Save details and upload selected files to send them together. Staff review your information before publication.</p>
    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl bg-stone-50 p-4">
            <h3 class="font-medium text-lamu-900">1. Property details</h3>
            <p class="mt-2 text-sm text-stone-600">Title, summary, description, property type, land use, tenure, ownership structure, county and locality, acreage, parcel reference and asking price. Lease listings also need a minimum lease duration.</p>
            @if ($dataGaps->isNotEmpty())
                <ul class="mt-3 list-disc space-y-1 pl-4 text-sm text-amber-900">@foreach ($dataGaps as $message)<li>{{ $message }}</li>@endforeach</ul>
            @else
                <p class="mt-3 text-sm font-medium text-lamu-800">Required listing details provided.</p>
            @endif
            @if ($canEdit)<a href="#owner-details" class="mt-3 inline-block text-sm font-medium text-lamu-800 underline">Complete all details below</a>@endif
        </div>
        <div class="rounded-xl bg-stone-50 p-4">
            <h3 class="font-medium text-lamu-900">2. Ownership documents</h3>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($requiredDocuments as $requirement)
                    <li>{{ $requirement['label'] }} <span class="font-medium {{ $requirement['supplied'] ? 'text-lamu-800' : 'text-amber-900' }}">· {{ $requirement['supplied'] ? 'Provided' : 'To provide' }}</span></li>
                @endforeach
            </ul>
            <a href="#documents" class="mt-3 inline-block text-sm font-medium text-lamu-800 underline">Select documents in bulk</a>
            <p class="mt-2 text-xs text-stone-600">The full checklist below also shows optional documents and checks handled by staff. Requirements update when the tenure or ownership details change.</p>
        </div>
        <div class="rounded-xl bg-stone-50 p-4">
            <h3 class="font-medium text-lamu-900">3. Photographs and submission</h3>
            <p class="mt-2 text-sm text-stone-600">Provide at least one photograph. You can select several images at once, up to 12 photos per listing.</p>
            <a href="#photos" class="mt-3 inline-block text-sm font-medium text-lamu-800 underline">Select photographs</a>
            <p class="mt-2 text-sm text-stone-600">After saving your details and files, check the remaining requirements and submit the listing for staff review.</p>
        </div>
    </div>
    @if ($canEdit)
        <details id="owner-details" class="mt-5 border-t border-sand-200 pt-4" @if ($dataGaps->isNotEmpty() || $errors->any()) open @endif>
            <summary class="cursor-pointer font-semibold text-lamu-900">Provide all listing details in one form</summary>
            @include('seller.properties.partials.form', [
                'action' => route('seller.properties.update', $property),
                'method' => 'PUT',
                'submitLabel' => 'Save details and upload selected files',
                'bulkSave' => true,
            ])
        </details>
    @else
        <p class="mt-4 text-sm text-stone-600">Staff manage changes to listing details at this stage. You can still provide supporting documents where uploads are available below.</p>
    @endif
</section>
