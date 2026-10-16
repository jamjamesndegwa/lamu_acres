@php
    $sellerApplication = $applications->get('seller');
    $brokerApplication = $applications->get('broker');
    $buyerWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'buyer');
    $investorWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'investor');
    $partnerApplications = [
        ['label' => 'Seller', 'capability' => 'seller', 'application' => $sellerApplication],
        ['label' => 'Broker', 'capability' => 'broker', 'application' => $brokerApplication],
    ];
@endphp

<section aria-labelledby="available-workspaces-heading">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 id="available-workspaces-heading" class="text-xl font-semibold text-lamu-900">Available workspaces</h2>
            <p class="mt-1 text-sm text-stone-600">Your account access determines which workspaces appear here.</p>
        </div>
        @if ($workspaces !== [])
            <span class="rounded-full bg-lamu-100 px-3 py-1 text-xs font-semibold text-lamu-900">{{ count($workspaces) }} {{ count($workspaces) === 1 ? 'workspace' : 'workspaces' }}</span>
        @endif
    </div>

    @if ($workspaces === [])
        <div class="mt-4 rounded-2xl border border-sand-200 bg-white p-6">
            <h3 class="font-semibold text-lamu-900">No workspace assigned yet</h3>
            <p class="mt-1 text-sm leading-6 text-stone-600">Contact the Lamu Acres team if you expected to have access to a workspace.</p>
        </div>
    @else
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($workspaces as $workspace)
                @php
                    $description = match ($workspace->value) {
                        'buyer' => 'Search properties and follow your enquiries, visits, and saved land.',
                        'seller' => 'Manage your properties, viewings, and buyer activity.',
                        'broker' => 'Manage listings, referrals, requirements, and deals.',
                        'investor' => 'Explore member research, market intelligence, and watchlists.',
                        'staff' => 'Manage assigned operations and service requests.',
                        'admin' => 'Manage access, approvals, business oversight, and settings.',
                        default => 'Open your Lamu Acres workspace.',
                    };
                    $isCurrent = ($current ?? null) === $workspace->value;
                @endphp

                <article @class([
                    'flex min-h-52 flex-col rounded-2xl border bg-white p-5 shadow-sm transition',
                    'border-lamu-700 ring-1 ring-lamu-700/10' => $isCurrent,
                    'border-sand-200 hover:border-lamu-700/50' => ! $isCurrent,
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-lamu-900">{{ $workspace->label() }}</h3>
                            <p class="mt-2 text-sm leading-6 text-stone-600">{{ $description }}</p>
                        </div>
                        @if ($isCurrent)
                            <span class="shrink-0 rounded-full bg-lamu-100 px-2.5 py-1 text-xs font-semibold text-lamu-900">Current</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('workspaces.switch', $workspace->value) }}" data-turbo="false" class="mt-auto pt-5">
                        @csrf
                        <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-full bg-lamu-900 px-4 text-sm font-semibold text-white transition hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">
                            {{ $isCurrent ? 'Continue to workspace' : 'Open workspace' }}
                        </button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
</section>

@if ($buyerWorkspace)
    <section class="mt-8 rounded-2xl border border-sand-200 bg-white p-5 sm:flex sm:items-center sm:justify-between sm:gap-6" aria-labelledby="investor-pass-heading">
        <div>
            <h2 id="investor-pass-heading" class="font-semibold text-lamu-900">Investor Pass</h2>
            <p class="mt-1 text-sm text-stone-600">{{ $investorPassStatus }} · Membership access to published investor research.</p>
        </div>
        @if ($investorWorkspace)
            <form method="POST" action="{{ route('workspaces.switch', $investorWorkspace->value) }}" data-turbo="false" class="mt-4 sm:mt-0">
                @csrf
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-full border border-lamu-900 px-4 text-sm font-semibold text-lamu-900 hover:bg-lamu-100">Open Investor Workspace</button>
            </form>
        @elseif (Route::has('buyer.subscription.show'))
            <a class="mt-4 inline-flex min-h-10 items-center justify-center rounded-full border border-lamu-900 px-4 text-sm font-semibold text-lamu-900 hover:bg-lamu-100 sm:mt-0" href="{{ route('buyer.subscription.show') }}">Manage Investor Pass</a>
        @endif
    </section>
@endif

@if ($canApplyForPartnerCapability || $sellerApplication || $brokerApplication)
    <section class="mt-10" aria-labelledby="partner-applications-heading">
        <div>
            <h2 id="partner-applications-heading" class="text-xl font-semibold text-lamu-900">Seller &amp; broker applications</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-stone-600">Partner access is reviewed separately. An application does not grant access until it is approved.</p>
        </div>

        @if (! $canApplyForPartnerCapability)
            <p class="mt-4 rounded-xl border border-sand-200 bg-sand-50 p-4 text-sm leading-6 text-stone-700">Staff and administration access is provisioned separately. This account cannot submit seller or broker applications.</p>
        @endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($partnerApplications as [$label, $capability, $application])
                @if ($canApplyForPartnerCapability || $application)
                    @php
                        $status = $application?->status === \App\Enums\CapabilityApplicationStatus::Pending
                            ? 'Under Review'
                            : ($application?->status?->label() ?? 'Not Applied');
                        $renewalRequired = $capability === 'broker' && $brokerRenewalRequired;
                    @endphp

                    <article class="rounded-2xl border border-sand-200 bg-white p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-lamu-900">{{ $label }}</h3>
                                <p class="mt-1 text-sm text-stone-600">{{ $renewalRequired ? 'Expired verification' : $status }}</p>
                            </div>
                            @if ($application?->status === \App\Enums\CapabilityApplicationStatus::Approved && ! $renewalRequired)
                                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Approved</span>
                            @elseif ($application?->status === \App\Enums\CapabilityApplicationStatus::Pending)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">In review</span>
                            @endif
                        </div>

                        @if ($application?->created_at)
                            <p class="mt-2 text-xs text-stone-500">Submitted {{ $application->created_at->toFormattedDateString() }}</p>
                        @endif
                        @if ($application?->decision_reason && in_array($application->status, [\App\Enums\CapabilityApplicationStatus::InformationRequired, \App\Enums\CapabilityApplicationStatus::Rejected, \App\Enums\CapabilityApplicationStatus::Suspended], true))
                            <p class="mt-3 text-sm leading-6 text-stone-700">{{ $application->decision_reason }}</p>
                        @endif
                        @if ($application?->status === \App\Enums\CapabilityApplicationStatus::Suspended)
                            <p class="mt-2 text-sm leading-6 text-stone-600">Contact the Lamu Acres team to request a review.</p>
                        @endif

                        @if ($canApplyForPartnerCapability)
                            <div class="mt-4 border-t border-sand-200 pt-4">
                                @if ($application === null)
                                    <a class="text-sm font-semibold text-lamu-800 underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Apply as {{ strtolower($label) }}</a>
                                @elseif ($renewalRequired)
                                    <a class="text-sm font-semibold text-lamu-800 underline" href="{{ route('account.applications.create', ['capability' => 'broker']) }}">Renew broker credentials</a>
                                @elseif ($application->status === \App\Enums\CapabilityApplicationStatus::InformationRequired)
                                    <a class="text-sm font-semibold text-lamu-800 underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Submit missing information</a>
                                @elseif ($application->status === \App\Enums\CapabilityApplicationStatus::Rejected)
                                    <a class="text-sm font-semibold text-lamu-800 underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Resubmit application</a>
                                @elseif (in_array($application->status, [\App\Enums\CapabilityApplicationStatus::Pending, \App\Enums\CapabilityApplicationStatus::Suspended], true))
                                    <a class="text-sm font-semibold text-lamu-800 underline" href="{{ route('account.applications.index') }}">View application</a>
                                @endif
                            </div>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>
    </section>
@endif
