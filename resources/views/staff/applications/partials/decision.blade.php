<p class="text-sm text-stone-600">{{ $application->user->name }} · {{ $application->capability->label() }} · {{ $application->status->label() }}</p>
<p class="mt-3 text-sm text-stone-800">{{ $application->statement }}</p>
@if ($application->organization)
    <p class="mt-2 text-sm text-stone-600">{{ $application->organization }}</p>
@endif

@php
    $profile = $application->verification ?? [];
@endphp
<section class="mt-4 space-y-3 rounded-xl border border-sand-200 p-4">
    <h2 class="font-semibold text-lamu-900">Verification evidence and outstanding checks</h2>
    <dl class="space-y-2 text-sm">
        <div><dt class="font-medium">Declared legal name / type</dt><dd>{{ $profile['legal_name'] ?? 'Not provided' }} · {{ $profile['applicant_type'] ?? 'Not provided' }}</dd></div>
        <div><dt class="font-medium">Account contact</dt><dd>{{ $application->user->email }} · {{ $application->user->email_verified_at ? 'Email verified' : 'Email not verified' }}</dd></div>
        <div><dt class="font-medium">Identity review</dt><dd>{{ $profile['identity']['outcome'] ?? 'Not reviewed' }} · Reviewer {{ $profile['identity']['reviewed_by'] ?? '—' }} · {{ $profile['identity']['reviewed_at'] ?? $profile['identity']['verified_at'] ?? 'No review date' }}</dd></div>
        <div><dt class="font-medium">Identity reference / next review</dt><dd>{{ $profile['identity']['reference'] ?? 'Not recorded' }} · {{ $profile['identity']['next_review_on'] ?? 'Not set' }}</dd></div>
        @if ($application->capability === \App\Enums\PartnerCapability::Broker)
            <div><dt class="font-medium">Self-declared EARB registration / certificate expiry</dt><dd>{{ $profile['earb_registration_number'] ?? 'Not provided' }} · {{ $profile['certificate_expires_on'] ?? 'Not provided' }}</dd></div>
            <div><dt class="font-medium">Professional standing review</dt><dd>{{ $profile['licence']['outcome'] ?? 'Not reviewed' }} · Reviewer {{ $profile['licence']['reviewed_by'] ?? '—' }} · {{ $profile['licence']['reviewed_at'] ?? $profile['licence']['verified_at'] ?? 'No review date' }}</dd></div>
            <div><dt class="font-medium">EARB reference / next review</dt><dd>{{ $profile['licence']['reference'] ?? 'Not recorded' }} · {{ $profile['licence']['next_review_on'] ?? 'Not set' }}</dd></div>
            <p><a href="https://estateagentsboard.or.ke/" target="_blank" rel="noopener noreferrer" class="font-medium text-lamu-800 underline">Check authoritative EARB records</a>. Uploading a certificate does not establish professional standing.</p>
        @endif
    </dl>
    <h3 class="pt-3 font-semibold text-lamu-900">Uploaded documents</h3>
    <p class="text-sm text-stone-600">Review the private document previews below before recording your checks. Open a document in a new tab for a larger view, or download it.</p>
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach (\App\Services\Access\CapabilityReviewer::EVIDENCE_TYPES as $kind)
            @if (isset($profile['evidence'][$kind]))
                @php
                    $document = $profile['evidence'][$kind];
                    $documentLabel = ucfirst(str_replace('_', ' ', $kind));
                    $previewUrl = route('account.applications.evidence', [$application, $kind, 'preview' => 1]);
                @endphp
                <article class="overflow-hidden rounded-xl border border-sand-200 bg-sand-50">
                    <h4 class="p-3 text-sm font-semibold text-lamu-900">{{ $documentLabel }}</h4>
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
                </article>
            @endif
        @endforeach
    </div>
    @if (empty($profile['evidence']))
        <p class="text-sm text-amber-900">No documents have been submitted. Request the required evidence before approval.</p>
    @endif
    @if ($verificationBlockers !== [])
        <ul class="list-disc space-y-1 pl-4 text-sm text-amber-900">@foreach ($verificationBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
    @else
        <p class="text-sm text-lamu-800">Applicable verification checks are current. A separate capability decision is still required.</p>
    @endif
</section>

<form method="POST" action="{{ route('staff.applications.update', $application) }}" data-turbo-frame="_top" data-confirm="Record this decision?" class="mt-5 space-y-4">
    @csrf
    @include('admin.approvals.return-field')
    @method('PUT')
    <fieldset class="space-y-4 rounded-xl border border-sand-200 p-4">
        <legend class="px-2 font-semibold">Record staff verification</legend>
        <p class="text-sm text-stone-600">Only record checks you have actually completed. For rejection or missing evidence, record the reason and choose Reject or Ask for information below. Do not paste identity numbers into references or reasons.</p>
        @foreach ($application->capability === \App\Enums\PartnerCapability::Broker ? ['identity' => 'Identity verification', 'licence' => 'EARB professional standing'] : ['identity' => 'Identity verification'] as $check => $label)
            <div class="space-y-2 border-t border-sand-200 pt-3">
                <label class="block text-sm">{{ $label }}
                    <select name="{{ $check }}_outcome" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        <option value="">Keep existing review</option>
                        @foreach (['verified' => 'Verified by staff', 'information_required' => 'Further information required', 'rejected' => 'Rejected evidence'] as $value => $name)<option value="{{ $value }}" @selected(old($check.'_outcome') === $value)>{{ $name }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm">Evidence / authoritative record reference
                    <input name="{{ $check }}_reference" maxlength="500" value="{{ old($check.'_reference') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                </label>
                <label class="block text-sm">Next review date (required when verified)
                    <input type="date" name="{{ $check }}_next_review_on" min="{{ today('Africa/Nairobi')->toDateString() }}" value="{{ old($check.'_next_review_on') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                </label>
            </div>
        @endforeach
        @if ($application->capability === \App\Enums\PartnerCapability::Broker)
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="earb_checked" value="1" @checked(old('earb_checked'))>I matched the legal name, registration number and certificate expiry against authoritative EARB records and confirmed current professional standing. I did not rely only on the uploaded certificate.</label>
        @endif
        @if ($errors->any())<p role="alert" class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
    </fieldset>
    <div>
        <label for="status" class="block text-sm font-medium">Decision</label>
        <select id="status" name="status" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach (['approved' => 'Approve', 'information_required' => 'Ask for information', 'rejected' => 'Reject', 'suspended' => 'Suspend capability'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="decision_reason" class="block text-sm font-medium">Reason</label>
        <textarea id="decision_reason" name="decision_reason" rows="3" required minlength="8" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('decision_reason') }}</textarea>
        @error('decision_reason')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record decision</button>
</form>

<section class="mt-5 rounded-xl border border-sand-200 p-4">
    <h2 class="font-semibold text-lamu-900">Review history</h2>
    <ul class="mt-3 space-y-3 text-sm">
        @forelse ($application->verification_history ?? [] as $entry)
            <li>{{ $entry['at'] ?? '' }} · {{ str_replace('_', ' ', $entry['event']) }} · Actor {{ $entry['actor_id'] }}
                @if (isset($entry['to']))<p>{{ $entry['from'] }} → {{ $entry['to'] }} · {{ $entry['reason'] }}</p>@endif
                @foreach ($entry['checks'] ?? [] as $check => $record)
                    @if ($record)<p>{{ ucfirst($check) }}: {{ $record['outcome'] ?? 'pending' }} · {{ $record['reference'] ?? 'No reference' }} · Next review {{ $record['next_review_on'] ?? 'Not set' }}</p>@endif
                @endforeach
            </li>
        @empty
            <li>No verification history. Existing capabilities must be reviewed before regulated access is granted.</li>
        @endforelse
    </ul>
</section>
