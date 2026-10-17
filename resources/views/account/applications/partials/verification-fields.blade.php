@php
    $profile = $application?->verification ?? [];
    $isBroker = $capability === \App\Enums\PartnerCapability::Broker;
    $types = $isBroker ? ['individual' => 'Individual practising agent', 'firm' => 'Firm — responsible practising agent'] : ['individual_owner' => 'Individual owner', 'authorized_representative' => 'Authorized representative', 'company_representative' => 'Company representative'];
@endphp
<fieldset class="space-y-4 rounded-xl border border-sand-200 p-4">
    <legend class="px-2 font-semibold text-lamu-900">Identity and {{ $isBroker ? 'professional standing' : 'authority' }}</legend>
    <p class="text-sm text-stone-600">We use this evidence to review identity and authority. Files are private to you and authorized review staff. Provide only relevant pages; do not add unnecessary personal data. Uploaded documents are not automatically verified.</p>
    <label class="block text-sm">Legal name {{ $isBroker ? 'of the practising agent or responsible principal' : 'of the applicant' }}
        <input name="legal_name" required maxlength="160" value="{{ old('legal_name', $profile['legal_name'] ?? auth()->user()->name) }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm">Applicant type
        <select name="applicant_type" required class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
            @foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('applicant_type', $profile['applicant_type'] ?? '') === $value)>{{ $label }}</option>@endforeach
        </select>
    </label>
    <x-upload-manager name="identity" label="Identity evidence (ID or passport, relevant pages only; PDF/JPG/PNG, up to 2 MB)" accept=".pdf,image/jpeg,image/png" :max-kilobytes="\App\Services\Access\CapabilityReviewer::MAX_EVIDENCE_KILOBYTES" required />
    @if ($isBroker)
        <p class="text-sm text-stone-600">The account email or phone must be verified before broker approval. For a firm, provide its legal organisation name below and the responsible agent's credentials. Submitting renewed credentials pauses new regulated brokerage actions until staff review them.</p>
        <label class="block text-sm">EARB registration number
            <input name="earb_registration_number" required maxlength="80" value="{{ old('earb_registration_number', $profile['earb_registration_number'] ?? '') }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm">Practising certificate expiry
            <input type="date" name="certificate_expires_on" required min="{{ today('Africa/Nairobi')->toDateString() }}" value="{{ old('certificate_expires_on', $profile['certificate_expires_on'] ?? '') }}" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <x-upload-manager name="practising_certificate" label="Current practising certificate (PDF/JPG/PNG, up to 2 MB)" accept=".pdf,image/jpeg,image/png" :max-kilobytes="\App\Services\Access\CapabilityReviewer::MAX_EVIDENCE_KILOBYTES" required />
    @else
        <p class="text-sm text-stone-600">Owners disposing of their own land do not need an EARB registration. Representatives must provide the owner's authorization. Company representatives also provide relevant company documentation.</p>
        <x-upload-manager name="representative_authority" label="Representative authority (required for representatives; PDF/JPG/PNG, up to 2 MB)" accept=".pdf,image/jpeg,image/png" :max-kilobytes="\App\Services\Access\CapabilityReviewer::MAX_EVIDENCE_KILOBYTES" />
    @endif
    <x-upload-manager name="business_document" label="Relevant business documentation (required for firms and company representatives; PDF/JPG/PNG, up to 2 MB)" accept=".pdf,image/jpeg,image/png" :max-kilobytes="\App\Services\Access\CapabilityReviewer::MAX_EVIDENCE_KILOBYTES" />
    <p class="text-xs text-stone-500">On reapplication, provide current evidence again. Previous decisions remain in the private review history.</p>
</fieldset>
