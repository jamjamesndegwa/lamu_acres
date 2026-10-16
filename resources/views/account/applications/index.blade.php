<x-account-layout title="Partner applications — Lamu Acres">
    <h1 class="text-2xl font-semibold text-lamu-900">Partner applications</h1>
    <p class="mt-2 text-sm text-stone-600">Apply for a broker or seller capability. Submitting an application does not grant the role.</p>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Apply as a broker</a>
        <a href="{{ route('account.applications.create', ['capability' => 'seller']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Apply as a seller</a>
    </div>

    <div class="mt-8 space-y-3">
        @forelse ($applications as $application)
            <article class="rounded-2xl border border-sand-200 bg-white p-4">
                <h2 class="font-semibold text-lamu-900">{{ $application->capability->label() }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ $application->status->label() }}</p>
                @if ($application->capability === \App\Enums\PartnerCapability::Broker)
                    <p class="mt-1 text-sm text-stone-600">Practising certificate expiry: {{ $application->verification['certificate_expires_on'] ?? 'Not supplied' }}. Uploads are self-declared until reviewed.</p>
                    @if ($application->status !== \App\Enums\CapabilityApplicationStatus::Suspended)
                        <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" class="mt-2 inline-block text-sm font-medium text-lamu-800 underline">Provide current or renewed broker credentials</a>
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
