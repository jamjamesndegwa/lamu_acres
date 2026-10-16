<x-account-layout title="Partner applications — Lamu Acres">
    <h1 class="text-2xl font-semibold text-lamu-900">Partner applications</h1>
    <p class="mt-2 text-sm text-stone-600">Apply for a broker or seller capability. Submitting an application does not grant the role.</p>
    @if ($canApply)
        <p class="mt-2 text-sm text-stone-600">Account capabilities: {{ $accountTypes->map(fn ($type) => ucfirst($type))->join(', ') }}. You can apply for another partner capability using this account.</p>
    @else
        <p class="mt-2 rounded-xl bg-sand-50 p-4 text-sm text-stone-700">This account has staff or administration access. Those roles are provisioned separately and cannot apply for seller or broker access here.</p>
    @endif

    <p class="mt-4 text-sm"><a href="{{ route('workspaces.index') }}" class="font-medium text-lamu-800 underline">Back to account capabilities</a></p>

    <div class="mt-8 space-y-3">
        @forelse ($applications as $application)
            <article class="rounded-2xl border border-sand-200 bg-white p-4">
                <h2 class="font-semibold text-lamu-900">{{ $application->capability->label() }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ $application->status === \App\Enums\CapabilityApplicationStatus::Pending ? 'Under Review' : $application->status->label() }} · Submitted {{ $application->created_at?->toFormattedDateString() }}</p>
                @if ($application->capability === \App\Enums\PartnerCapability::Broker)
                    <p class="mt-1 text-sm text-stone-600">Practising certificate expiry: {{ $application->verification['certificate_expires_on'] ?? 'Not supplied' }}. Uploads are self-declared until reviewed.</p>
                @endif

                @if ($canApply)
                    @if ($application->status === \App\Enums\CapabilityApplicationStatus::Approved && $application->capability === \App\Enums\PartnerCapability::Broker && app(\App\Services\Access\CapabilityReviewer::class)->verificationBlockers($application) !== [])
                        <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" class="mt-2 inline-block text-sm font-medium text-lamu-800 underline">Renew Broker Credentials</a>
                    @elseif (in_array($application->status, [\App\Enums\CapabilityApplicationStatus::InformationRequired, \App\Enums\CapabilityApplicationStatus::Rejected], true))
                        <a href="{{ route('account.applications.create', ['capability' => $application->capability->value]) }}" class="mt-2 inline-block text-sm font-medium text-lamu-800 underline">{{ $application->status === \App\Enums\CapabilityApplicationStatus::InformationRequired ? 'Submit Missing Information' : 'Resubmit Application' }}</a>
                    @elseif ($application->status === \App\Enums\CapabilityApplicationStatus::Suspended)
                        <p class="mt-2 text-sm text-stone-600">Contact the Lamu Acres team to request a review.</p>
                    @endif
                @endif

                @if ($application->decision_reason)
                    <p class="mt-2 text-sm text-stone-700">{{ $application->decision_reason }}</p>
                @endif
            </article>
        @empty
            <p class="text-sm text-stone-600">No application has been submitted.</p>
        @endforelse
    </div>
</x-account-layout>
