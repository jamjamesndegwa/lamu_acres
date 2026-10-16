@php
    $profile = $application->verification ?? [];
    $applicant = $application->user;
    $isBroker = $application->capability === \App\Enums\PartnerCapability::Broker;
    $contactVerified = (filled($applicant->email) && $applicant->email_verified_at !== null) || (filled($applicant->phone) && $applicant->phone_verified_at !== null);
    $isCompany = in_array($profile['applicant_type'] ?? '', ['firm', 'company_representative'], true);
    $isRepresentative = in_array($profile['applicant_type'] ?? '', ['authorized_representative', 'company_representative'], true);
@endphp
<x-smooth-section-scroll />
<section class="rounded-xl bg-sand-50 p-4">
    <h2 class="home-serif text-xl font-semibold text-lamu-900">{{ $applicant->name }} · {{ $application->capability->label() }}</h2>
    <p class="mt-1 text-sm text-stone-600">Application status: {{ $application->status->label() }} · Submitted {{ $application->created_at?->format('d M Y, H:i') }}</p>
    <p class="mt-3 text-sm text-stone-700">{{ $application->statement }}</p>
    <nav class="mt-4 flex flex-wrap gap-3 text-xs font-semibold text-lamu-800" aria-label="Application verification steps">
        <a class="underline" href="#review-contact">1. Account contact</a>
        <a class="underline" href="#review-identity">2. Identity</a>
        <a class="underline" href="#review-standing">3. {{ $isBroker ? 'Professional standing' : 'Authority' }}</a>
        <a class="underline" href="#review-decision">4. Decision</a>
    </nav>
</section>
@if ($errors->any())
    <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-semibold text-red-900">The review could not be saved</p>
        <ul class="mt-2 list-disc pl-4 text-sm text-red-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<section id="review-contact" class="mt-4 scroll-mt-24 space-y-3 rounded-xl border border-sand-200 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Step 1 · Verify account contact</h2>
        <x-status-badge :label="$contactVerified ? 'Verified contact available' : ($isBroker ? 'Approval blocked' : 'Contact not yet verified')" />
    </div>
    <p class="text-sm text-stone-600">{{ $isBroker ? 'At least one verified email or phone is required before broker approval.' : 'Check the applicant contact details. Identity and authority checks below remain required for seller approval.' }} Contact confirmation proves access to an inbox or phone; it does not verify identity or professional standing.</p>
    <dl class="grid gap-4 text-sm sm:grid-cols-2">
        <div class="rounded-lg bg-sand-50 p-3"><dt class="font-semibold text-lamu-900">Account email</dt><dd class="mt-1 break-words">{{ $applicant->email }}</dd><dd class="mt-1 text-xs text-stone-600">{{ $applicant->email_verified_at ? 'Verified on '.$applicant->email_verified_at->format('d M Y, H:i') : 'Not verified' }}</dd></div>
        <div class="rounded-lg bg-sand-50 p-3"><dt class="font-semibold text-lamu-900">Account phone</dt><dd class="mt-1">{{ $applicant->phone ?: 'Not provided' }}</dd><dd class="mt-1 text-xs text-stone-600">{{ $applicant->phone_verified_at ? 'Verified on '.$applicant->phone_verified_at->format('d M Y, H:i') : 'Not verified' }}</dd>@if ($applicant->pending_phone)<dd class="mt-1 text-xs text-amber-900">Awaiting code confirmation: {{ $applicant->pending_phone }}</dd>@endif</div>
    </dl>
    <ol class="list-decimal space-y-1 pl-5 text-sm text-stone-600">
        <li>Send the verification email to the applicant's saved email address.</li>
        <li>The applicant opens the link from their inbox while signed in to their own account.</li>
        <li>Refresh this review page to see the verified timestamp. Alternatively, the applicant confirms a phone number with a delivered OTP in account Settings.</li>
    </ol>
    @if ($applicant->email_verified_at === null)
        @if (\App\Notifications\AccountEmailChanged::canDeliverEmail())
            <form method="POST" action="{{ route('staff.applications.email-verification', $application) }}" data-turbo-frame="_top">
                @csrf
                <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send verification email to applicant</button>
            </form>
        @else
            <p class="rounded-lg bg-sand-50 p-3 text-sm text-amber-900">Email delivery is not configured. Phone verification also requires a working OTP delivery service. {{ config('lamu.allow_manual_contact_verification') ? 'Staff-recorded verification is enabled below for contacts independently confirmed by authorized staff.' : 'Ask the system administrator to configure delivery or enable audited staff-recorded contact verification.' }}</p>
        @endif
    @endif
    @if (config('lamu.allow_manual_contact_verification'))
        <div class="space-y-3 border-t border-sand-200 pt-4">
            <h3 class="font-semibold text-lamu-900">Record contact verification manually</h3>
            <p class="text-sm text-stone-600">Use this only after independently confirming that the applicant controls the saved email or phone. Record how it was confirmed and an evidence reference. This is recorded as a staff decision, not an email-link or OTP confirmation.</p>
            @foreach (['email' => 'Email', 'phone' => 'Phone'] as $channel => $label)
                @if (filled($applicant->getAttribute($channel)) && $applicant->getAttribute($channel.'_verified_at') === null)
                    <form method="POST" action="{{ route('staff.applications.contact-verification', $application) }}" data-turbo-frame="_top" data-confirm="Record this contact as manually verified?" class="space-y-3 rounded-lg bg-sand-50 p-4">
                        @csrf
                        <input type="hidden" name="contact_channel" value="{{ $channel }}">
                        <input type="hidden" name="contact_value" value="{{ $applicant->getAttribute($channel) }}">
                        <p class="text-sm font-semibold text-lamu-900">{{ $label }}: {{ $applicant->getAttribute($channel) }}</p>
                        <label class="block text-sm">Verification method and evidence reference
                            <textarea name="verification_reference" required minlength="8" maxlength="500" rows="2" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2" placeholder="Describe the confirmation method and give an internal evidence reference."></textarea>
                        </label>
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="contact_confirmed" value="1" required>I independently confirmed that the applicant controls this {{ strtolower($label) }} and recorded the supporting reference.</label>
                        <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Record {{ strtolower($label) }} as verified</button>
                    </form>
                @endif
            @endforeach
        </div>
    @endif
</section>

<form method="POST" action="{{ route('staff.applications.update', $application) }}" data-turbo-frame="_top" data-confirm="Record this decision?" class="mt-4 space-y-4">
    @csrf
    @include('admin.approvals.return-field')
    @method('PUT')
    <section id="review-identity" class="scroll-mt-24 space-y-4 rounded-xl border border-sand-200 p-4">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Step 2 · Verify identity</h2>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold">Account name</dt><dd>{{ $applicant->name }}</dd></div>
            <div><dt class="font-semibold">Declared legal name</dt><dd>{{ $profile['legal_name'] ?? 'Not provided' }}</dd></div>
            <div><dt class="font-semibold">Applicant type</dt><dd>{{ str_replace('_', ' ', $profile['applicant_type'] ?? 'Not provided') }}</dd></div>
            <div><dt class="font-semibold">Organisation</dt><dd>{{ $application->organization ?: 'Not provided / individual applicant' }}</dd></div>
        </dl>
        <h3 class="text-sm font-semibold text-lamu-900">Uploaded documents · Identity evidence</h3>
        <p class="text-sm text-stone-600">Compare the legal name and applicant details with the ID or passport below. Check that the evidence is readable and relates to the person applying. Record a review reference; do not paste identity numbers into reasons.</p>
        @include('staff.applications.partials.evidence', ['kind' => 'identity', 'required' => true])
        <h3 class="text-sm font-semibold text-lamu-900">Record staff verification</h3>
        @include('staff.applications.partials.verification-check', ['check' => 'identity', 'label' => 'Identity verification'])
    </section>

    <section id="review-standing" class="scroll-mt-24 space-y-4 rounded-xl border border-sand-200 p-4">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Step 3 · {{ $isBroker ? 'Verify professional standing' : 'Check representative authority' }}</h2>
        @if ($isBroker)
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">Self-declared EARB registration</dt><dd>{{ $profile['earb_registration_number'] ?? 'Not provided' }}</dd></div>
                <div><dt class="font-semibold">Practising certificate expiry</dt><dd>{{ $profile['certificate_expires_on'] ?? 'Not provided' }}</dd></div>
            </dl>
            <p class="text-sm text-stone-600">Check the responsible agent's legal name, registration number, certificate validity and current professional standing against authoritative EARB records. An uploaded certificate alone is not verification.</p>
            <a href="https://estateagentsboard.or.ke/" target="_blank" rel="noopener noreferrer" class="inline-block text-sm font-semibold text-lamu-800 underline">Check authoritative EARB records</a>
            @include('staff.applications.partials.evidence', ['kind' => 'practising_certificate', 'required' => true])
            @if ($isCompany || isset($profile['evidence']['business_document']))
                @include('staff.applications.partials.evidence', ['kind' => 'business_document', 'required' => $isCompany])
            @endif
            @include('staff.applications.partials.verification-check', ['check' => 'licence', 'label' => 'EARB professional standing'])
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="earb_checked" value="1" @checked(old('earb_checked'))>I matched the legal name, registration number and certificate expiry against authoritative EARB records and confirmed current professional standing. I did not rely only on the uploaded certificate.</label>
        @else
            <p class="text-sm text-stone-600">An owner disposing of their own property does not need EARB registration. Representatives must show authority to act; company representatives must also provide applicable company evidence.</p>
            @if ($isRepresentative || isset($profile['evidence']['representative_authority']))
                @include('staff.applications.partials.evidence', ['kind' => 'representative_authority', 'required' => $isRepresentative])
            @endif
            @if ($isCompany || isset($profile['evidence']['business_document']))
                @include('staff.applications.partials.evidence', ['kind' => 'business_document', 'required' => $isCompany])
            @endif
            <p class="text-sm text-stone-600">Record your authority assessment in the review reference and final decision reason. Property ownership and marketing authority are checked separately for each listing.</p>
        @endif
    </section>

    <section id="review-decision" class="scroll-mt-24 space-y-4 rounded-xl border border-sand-200 p-4">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Step 4 · Review blockers and record a decision</h2>
        @if ($verificationBlockers !== [])
            <div class="rounded-lg bg-sand-50 p-3">
                <h3 class="text-sm font-semibold text-amber-900">Outstanding checks in the saved review</h3>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-amber-900">@foreach ($verificationBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
            </div>
        @else
            <p class="text-sm text-lamu-800">Applicable verification checks are current. A separate capability decision is still required.</p>
        @endif
        <p class="text-xs text-stone-600">Checks entered above are validated when you record the decision. Approval remains blocked if any applicable requirement is unsatisfied. Choose Ask for information when the applicant must provide missing evidence or confirm their contact.</p>
        <label class="block text-sm font-medium">Decision
            <select id="status" name="status" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Choose a decision</option>
                @foreach (['approved' => 'Approve', 'information_required' => 'Ask for information', 'rejected' => 'Reject', 'suspended' => 'Suspend capability'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status') === $value) @disabled($value === 'approved' && $isBroker && ! $contactVerified)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($isBroker && ! $contactVerified)<p class="text-sm text-amber-900">Approve is unavailable until the applicant verifies an email or phone. Complete Step 1, then refresh this page.</p>@endif
        <label for="decision_reason" class="block text-sm font-medium">Reason
            <textarea id="decision_reason" name="decision_reason" rows="3" required minlength="8" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('decision_reason') }}</textarea>
        </label>
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record decision</button>
    </section>
</form>

<section class="mt-5 rounded-xl border border-sand-200 p-4">
    <h2 class="font-semibold text-lamu-900">Review history</h2>
    <ul class="mt-3 space-y-3 text-sm">
        @forelse ($application->verification_history ?? [] as $entry)
            <li>{{ $entry['at'] ?? '' }} · {{ str_replace('_', ' ', $entry['event']) }} · Actor {{ $entry['actor_id'] }}
                @if (isset($entry['to']))<p>{{ $entry['from'] }} → {{ $entry['to'] }} · {{ $entry['reason'] }}</p>@endif
                @if (($entry['event'] ?? '') === 'manual_contact_verified')<p>Staff-recorded {{ $entry['channel'] }} verification · {{ $entry['reference'] }}</p>@endif
                @foreach ($entry['checks'] ?? [] as $check => $record)
                    @if ($record)<p>{{ ucfirst($check) }}: {{ $record['outcome'] ?? 'pending' }} · {{ $record['reference'] ?? 'No reference' }} · Next review {{ $record['next_review_on'] ?? 'Not set' }}</p>@endif
                @endforeach
            </li>
        @empty
            <li>No verification history. Existing capabilities must be reviewed before regulated access is granted.</li>
        @endforelse
    </ul>
</section>
