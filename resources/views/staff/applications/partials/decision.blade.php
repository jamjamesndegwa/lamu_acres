@php
    $profile = $application->verification ?? [];
    $applicant = $application->user;
    $isBroker = $application->capability === \App\Enums\PartnerCapability::Broker;
    $contactVerified = (filled($applicant->email) && $applicant->email_verified_at !== null) || (filled($applicant->phone) && $applicant->phone_verified_at !== null);
    $isCompany = in_array($profile['applicant_type'] ?? '', ['firm', 'company_representative'], true);
    $isRepresentative = in_array($profile['applicant_type'] ?? '', ['authorized_representative', 'company_representative'], true);
    $checklist = $reviewChecklist ?? [];
    $blockers = $verificationBlockers ?? [];
    $pendingCount = collect($checklist)->where('state', 'pending')->count();
    $readyToApprove = $blockers === [];
@endphp
<x-smooth-section-scroll />

<header class="overflow-hidden rounded-2xl border border-lamu-800 bg-lamu-900 p-6 text-white shadow-md">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-white/75">{{ $application->capability->label() }} application</p>
            <h1 class="home-serif mt-1 text-2xl font-semibold text-white">{{ $applicant->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-white/90">{{ $application->statement }}</p>
        </div>
        <x-status-badge :label="$application->status->label()" class="shrink-0 !bg-white/15 !text-white ring-1 ring-white/20" />
    </div>
    <dl class="mt-5 grid gap-3 text-sm text-white sm:grid-cols-3">
        <div class="rounded-lg bg-black/20 px-3 py-2 ring-1 ring-white/10">
            <dt class="text-xs font-semibold uppercase tracking-wide text-white/70">Submitted</dt>
            <dd class="mt-1 font-medium text-white">{{ $application->created_at?->format('d M Y, H:i') }}</dd>
        </div>
        <div class="rounded-lg bg-black/20 px-3 py-2 ring-1 ring-white/10">
            <dt class="text-xs font-semibold uppercase tracking-wide text-white/70">Legal name</dt>
            <dd class="mt-1 font-medium text-white">{{ $profile['legal_name'] ?? '—' }}</dd>
        </div>
        <div class="rounded-lg bg-black/20 px-3 py-2 ring-1 ring-white/10">
            <dt class="text-xs font-semibold uppercase tracking-wide text-white/70">Applicant type</dt>
            <dd class="mt-1 font-medium capitalize text-white">{{ str_replace('_', ' ', $profile['applicant_type'] ?? '—') }}</dd>
        </div>
    </dl>
</header>

<section class="mt-6 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm" aria-labelledby="review-checklist-heading">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="review-checklist-heading" class="home-serif text-lg font-semibold text-lamu-900">Review checklist</h2>
        @if ($readyToApprove)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900">Ready to approve</span>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{{ $pendingCount }} item{{ $pendingCount === 1 ? '' : 's' }} still outstanding</span>
        @endif
    </div>
    <p class="mt-1 text-sm text-stone-600">Complete each pending item by reviewing documents and setting verification outcomes below. Email and phone verification are optional and do not block approval.</p>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ($checklist as $item)
            <li @class([
                'flex gap-3 rounded-xl border p-4',
                'border-emerald-200 bg-emerald-50/60' => $item['state'] === 'complete',
                'border-amber-200 bg-amber-50/50' => $item['state'] === 'pending',
            ])>
                <span @class([
                    'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-emerald-600 text-white' => $item['state'] === 'complete',
                    'bg-amber-500 text-white' => $item['state'] === 'pending',
                ]) aria-hidden="true">{{ $item['state'] === 'complete' ? '✓' : '!' }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-lamu-900">{{ $item['label'] }}</p>
                    <p class="mt-0.5 text-xs text-stone-600">{{ $item['detail'] }}</p>
                </div>
            </li>
        @endforeach
    </ul>
    @if ($blockers !== [])
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4" role="status">
            <p class="text-sm font-semibold text-amber-950">Before you can approve</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                @foreach ($blockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</section>

@if ($errors->any())
    <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-semibold text-red-900">The review could not be saved</p>
        <ul class="mt-2 list-disc pl-4 text-sm text-red-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<nav class="mt-6 flex flex-wrap gap-2 text-xs font-semibold text-lamu-800" aria-label="Application verification steps">
    <a class="rounded-full border border-sand-200 bg-white px-3 py-1.5 shadow-sm hover:border-lamu-300" href="#review-identity">Documents & identity</a>
    <a class="rounded-full border border-sand-200 bg-white px-3 py-1.5 shadow-sm hover:border-lamu-300" href="#review-standing">{{ $isBroker ? 'Professional standing' : 'Authority' }}</a>
    <a class="rounded-full border border-sand-200 bg-white px-3 py-1.5 shadow-sm hover:border-lamu-300" href="#review-decision">Decision</a>
    <a class="rounded-full border border-sand-200 bg-white px-3 py-1.5 shadow-sm hover:border-lamu-300" href="#review-contact">Contact (optional)</a>
</nav>

<form method="POST" action="{{ route('staff.applications.update', $application) }}" data-turbo-frame="_top" data-confirm="Record this decision?" class="mt-4 space-y-6">
    @csrf
    @include('admin.approvals.return-field')
    @method('PUT')

    <section id="review-identity" class="scroll-mt-24 space-y-4 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Identity documents</h2>
        <p class="text-sm text-stone-600">Compare the declared legal name and applicant type with the ID or passport. Mark identity as verified when the evidence is acceptable.</p>
        <dl class="grid gap-3 rounded-xl bg-sand-50 p-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-lamu-900">Account display name</dt><dd class="mt-1">{{ $applicant->name }}</dd></div>
            <div><dt class="font-semibold text-lamu-900">Organisation</dt><dd class="mt-1">{{ $application->organization ?: 'Individual applicant' }}</dd></div>
        </dl>
        @include('staff.applications.partials.evidence', ['kind' => 'identity', 'required' => true])
        @include('staff.applications.partials.verification-check', ['check' => 'identity', 'label' => 'Identity verification'])
    </section>

    <section id="review-standing" class="scroll-mt-24 space-y-4 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">{{ $isBroker ? 'Professional standing' : 'Representative authority' }}</h2>
        @if ($isBroker)
            <dl class="grid gap-3 rounded-xl bg-sand-50 p-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold text-lamu-900">EARB registration (declared)</dt><dd class="mt-1 font-mono text-sm">{{ $profile['earb_registration_number'] ?? 'Not provided' }}</dd></div>
                <div><dt class="font-semibold text-lamu-900">Certificate expires</dt><dd class="mt-1">{{ $profile['certificate_expires_on'] ?? 'Not provided' }}</dd></div>
            </dl>
            <p class="text-sm text-stone-600">Review the practising certificate and registration details in the documents below. You may cross-check EARB if helpful — it is not required to record a reference number here.</p>
            <a href="https://estateagentsboard.or.ke/" target="_blank" rel="noopener noreferrer" class="inline-flex text-sm font-semibold text-lamu-800 underline">Open EARB website (optional)</a>
            @include('staff.applications.partials.evidence', ['kind' => 'practising_certificate', 'required' => true])
            @if ($isCompany || isset($profile['evidence']['business_document']))
                @include('staff.applications.partials.evidence', ['kind' => 'business_document', 'required' => $isCompany])
            @endif
            @include('staff.applications.partials.verification-check', ['check' => 'licence', 'label' => 'Professional standing'])
        @else
            <p class="text-sm text-stone-600">Owners selling their own land do not need EARB registration. Representatives must show authority to act; company representatives also need business evidence where applicable.</p>
            @if ($isRepresentative || isset($profile['evidence']['representative_authority']))
                @include('staff.applications.partials.evidence', ['kind' => 'representative_authority', 'required' => $isRepresentative])
            @endif
            @if ($isCompany || isset($profile['evidence']['business_document']))
                @include('staff.applications.partials.evidence', ['kind' => 'business_document', 'required' => $isCompany])
            @endif
        @endif
    </section>

    <section id="review-decision" class="scroll-mt-24 space-y-4 rounded-2xl border border-lamu-200 bg-lamu-50/40 p-5 shadow-sm">
        <h2 class="home-serif text-lg font-semibold text-lamu-900">Record decision</h2>
        <p class="text-sm text-stone-600">Choose approve when the checklist is complete. Use “Ask for information” when the applicant must upload or clarify something.</p>
        <label class="block text-sm font-medium text-lamu-900">Decision
            <select id="status" name="status" required class="mt-1 w-full max-w-md rounded-lg border border-sand-200 bg-white px-3 py-2 shadow-sm">
                <option value="">Choose a decision</option>
                @foreach (['approved' => 'Approve '.$application->capability->label(), 'information_required' => 'Ask for information', 'rejected' => 'Reject', 'suspended' => 'Suspend capability'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label for="decision_reason" class="block text-sm font-medium text-lamu-900">Reason (visible to applicant)
            <textarea id="decision_reason" name="decision_reason" rows="3" required minlength="8" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 shadow-sm" placeholder="Briefly explain what you reviewed and why you reached this decision.">{{ old('decision_reason') }}</textarea>
        </label>
        <button type="submit" class="rounded-full bg-lamu-900 px-6 py-3 text-sm font-semibold text-white shadow hover:bg-lamu-800">Save review & decision</button>
    </section>
</form>

<details id="review-contact" class="group mt-6 scroll-mt-24 rounded-2xl border border-sand-200 bg-white shadow-sm">
    <summary class="cursor-pointer list-none rounded-2xl p-5 marker:content-none">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="home-serif text-lg font-semibold text-lamu-900">Account contact <span class="text-sm font-normal text-stone-500">(optional)</span></h2>
            <x-status-badge :label="$contactVerified ? 'Verified contact on file' : 'Not verified — approval still allowed'" />
        </div>
        <p class="mt-2 text-sm text-stone-600 group-open:hidden">Expand to send verification email or record manual contact confirmation. This is not required to approve seller or broker applications.</p>
    </summary>
    <div class="space-y-4 border-t border-sand-100 px-5 pb-5 pt-4">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div class="rounded-lg bg-sand-50 p-3"><dt class="font-semibold text-lamu-900">Email</dt><dd class="mt-1 break-words">{{ $applicant->email }}</dd><dd class="mt-1 text-xs text-stone-600">{{ $applicant->email_verified_at ? 'Verified '.$applicant->email_verified_at->format('d M Y') : 'Not verified' }}</dd></div>
            <div class="rounded-lg bg-sand-50 p-3"><dt class="font-semibold text-lamu-900">Phone</dt><dd class="mt-1">{{ $applicant->phone ?: 'Not provided' }}</dd><dd class="mt-1 text-xs text-stone-600">{{ $applicant->phone_verified_at ? 'Verified '.$applicant->phone_verified_at->format('d M Y') : 'Not verified' }}</dd>@if ($applicant->pending_phone)<dd class="mt-1 text-xs text-amber-900">Pending: {{ $applicant->pending_phone }}</dd>@endif</div>
        </dl>
        @if ($applicant->email_verified_at === null)
            @if (\App\Notifications\AccountEmailChanged::canDeliverEmail())
                <form method="POST" action="{{ route('staff.applications.email-verification', $application) }}" data-turbo-frame="_top">
                    @csrf
                    <button type="submit" class="rounded-full border border-lamu-800 bg-white px-4 py-2 text-sm font-medium text-lamu-900">Send verification email</button>
                </form>
            @else
                <p class="rounded-lg bg-sand-50 p-3 text-sm text-amber-900">Email delivery is not configured. The applicant can verify a phone number in account settings when OTP delivery is available.</p>
            @endif
        @endif
        @if (config('lamu.allow_manual_contact_verification'))
            <div class="space-y-3 border-t border-sand-200 pt-4">
                <h3 class="text-sm font-semibold text-lamu-900">Manual contact verification</h3>
                @foreach (['email' => 'Email', 'phone' => 'Phone'] as $channel => $label)
                    @if (filled($applicant->getAttribute($channel)) && $applicant->getAttribute($channel.'_verified_at') === null)
                        <form method="POST" action="{{ route('staff.applications.contact-verification', $application) }}" data-turbo-frame="_top" data-confirm="Record this contact as manually verified?" class="space-y-3 rounded-lg bg-sand-50 p-4">
                            @csrf
                            <input type="hidden" name="contact_channel" value="{{ $channel }}">
                            <input type="hidden" name="contact_value" value="{{ $applicant->getAttribute($channel) }}">
                            <p class="text-sm font-semibold text-lamu-900">{{ $label }}: {{ $applicant->getAttribute($channel) }}</p>
                            <label class="block text-sm">How contact was confirmed
                                <textarea name="verification_reference" required minlength="8" maxlength="500" rows="2" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2"></textarea>
                            </label>
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="contact_confirmed" value="1" required>I confirmed the applicant controls this {{ strtolower($label) }}.</label>
                            <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Record as verified</button>
                        </form>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</details>

<section class="mt-6 rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
    <h2 class="font-semibold text-lamu-900">Review history</h2>
    <ul class="mt-3 space-y-3 text-sm text-stone-700">
        @forelse ($application->verification_history ?? [] as $entry)
            <li class="rounded-lg border border-sand-100 bg-sand-50/50 p-3">
                <span class="font-medium text-lamu-900">{{ str_replace('_', ' ', $entry['event'] ?? 'event') }}</span>
                · {{ $entry['at'] ?? '' }}
                @if (isset($entry['to']))<p class="mt-1">{{ $entry['from'] }} → {{ $entry['to'] }} · {{ $entry['reason'] }}</p>@endif
                @foreach ($entry['checks'] ?? [] as $check => $record)
                    @if ($record)<p class="mt-1 text-xs">{{ ucfirst($check) }}: {{ $record['outcome'] ?? 'pending' }}</p>@endif
                @endforeach
            </li>
        @empty
            <li class="text-stone-600">No verification history yet.</li>
        @endforelse
    </ul>
</section>
