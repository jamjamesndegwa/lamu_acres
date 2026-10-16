@php
    $sellerApplication = $applications->get('seller');
    $brokerApplication = $applications->get('broker');
    $buyerWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'buyer');
    $sellerWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'seller');
    $brokerWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'broker');
    $investorWorkspace = collect($workspaces)->first(fn ($workspace) => $workspace->value === 'investor');
    $sellerStatus = $sellerApplication?->status === \App\Enums\CapabilityApplicationStatus::Pending ? 'Under Review' : ($sellerApplication?->status?->label() ?? 'Not Applied');
    $brokerStatus = $brokerRenewalRequired ? 'Expired Verification' : ($brokerApplication?->status === \App\Enums\CapabilityApplicationStatus::Pending ? 'Under Review' : ($brokerApplication?->status?->label() ?? 'Not Applied'));
@endphp

<section aria-labelledby="account-capabilities-heading">
    <h2 id="account-capabilities-heading" class="text-lg font-semibold text-lamu-900">Your account</h2>
    @if (! $canApplyForPartnerCapability)
        <p class="mt-2 text-sm text-stone-600">Seller and broker applications are available to buyer, seller, and broker accounts. Staff and administration access is provisioned separately.</p>
    @endif
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <article class="rounded-2xl border border-sand-200 bg-white p-4">
            <h3 class="font-medium text-lamu-900">Buyer</h3>
            <p class="mt-1 text-sm text-stone-600">{{ $buyerWorkspace ? 'Active' : 'Not active' }}</p>
        </article>

        @foreach ([['Seller', $sellerApplication, $sellerStatus, $sellerWorkspace, 'seller'], ['Broker', $brokerApplication, $brokerStatus, $brokerWorkspace, 'broker']] as [$label, $application, $status, $workspace, $capability])
            <article class="rounded-2xl border border-sand-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium text-lamu-900">{{ $label }}</h3>
                        <p class="mt-1 text-sm text-stone-600">{{ $status }}</p>
                        @if ($application?->created_at)
                            <p class="mt-1 text-xs text-stone-500">Submitted {{ $application->created_at->toFormattedDateString() }}</p>
                        @endif
                        @if ($application?->decision_reason && in_array($application->status, [\App\Enums\CapabilityApplicationStatus::InformationRequired, \App\Enums\CapabilityApplicationStatus::Rejected, \App\Enums\CapabilityApplicationStatus::Suspended], true))
                            <p class="mt-2 text-sm text-stone-700">{{ $application->decision_reason }}</p>
                        @endif
                        @if ($application?->status === \App\Enums\CapabilityApplicationStatus::Suspended)
                            <p class="mt-2 text-sm text-stone-600">Contact the Lamu Acres team to request a review. Other approved workspaces remain available.</p>
                        @endif
                    </div>
                    @if ($workspace)
                        @if ($capability === 'broker' && ($brokerRenewalRequired || $application?->status === \App\Enums\CapabilityApplicationStatus::Pending))
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900">Business actions restricted</span>
                        @else
                            <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Authorized</span>
                        @endif
                    @endif
                </div>
                @if ($canApplyForPartnerCapability)
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm font-medium text-lamu-800">
                    @if ($application === null)
                        <a class="underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Apply as {{ strtolower($label) }}</a>
                    @elseif ($status === 'Under Review' || $application->status === \App\Enums\CapabilityApplicationStatus::Suspended)
                        <a class="underline" href="{{ route('account.applications.index') }}">View Application</a>
                    @elseif ($application->status === \App\Enums\CapabilityApplicationStatus::InformationRequired)
                        <a class="underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Submit Missing Information</a>
                    @elseif ($application->status === \App\Enums\CapabilityApplicationStatus::Rejected)
                        <a class="underline" href="{{ route('account.applications.create', ['capability' => $capability]) }}">Resubmit Application</a>
                    @elseif ($brokerRenewalRequired)
                        <a class="underline" href="{{ route('account.applications.create', ['capability' => 'broker']) }}">Renew Broker Credentials</a>
                    @elseif ($workspace)
                        <a class="underline" href="{{ route($workspace->homeRoute()) }}">Open {{ $label }} Workspace</a>
                    @else
                        <a class="underline" href="{{ route('account.applications.index') }}">View Application</a>
                    @endif
                    </div>
                @endif
            </article>
        @endforeach

        <article class="rounded-2xl border border-sand-200 bg-white p-4">
            <h3 class="font-medium text-lamu-900">Investor Pass</h3>
            <p class="mt-1 text-sm text-stone-600">{{ $investorPassStatus }}</p>
            @if ($investorWorkspace)
                <form method="POST" action="{{ route('workspaces.switch', $investorWorkspace->value) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-lamu-800 underline">Open Investor Workspace</button>
                </form>
            @elseif (Route::has('buyer.subscription.show'))
                <a class="mt-3 inline-block text-sm font-medium text-lamu-800 underline" href="{{ route('buyer.subscription.show') }}">View Investor Pass</a>
            @endif
        </article>
    </div>
</section>

<section class="mt-8" aria-labelledby="available-workspaces-heading">
    <h2 id="available-workspaces-heading" class="text-lg font-semibold text-lamu-900">Available workspaces</h2>
    @if ($workspaces === [])
        <p class="mt-3 text-stone-700">No workspace has been assigned to this account.</p>
    @else
        <div class="mt-3 space-y-3">
            @foreach ($workspaces as $workspace)
                <form method="POST" action="{{ route('workspaces.switch', $workspace->value) }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between rounded-2xl border border-sand-200 bg-white px-5 py-4 text-left hover:border-lamu-700">
                        <span>
                            <span class="block font-medium text-lamu-900">{{ $workspace->label() }}</span>
                            <span class="mt-1 block text-sm text-stone-600">{{ ($current ?? null) === $workspace->value ? 'Current workspace' : 'Open this workspace' }}</span>
                        </span>
                        <span class="text-sm font-medium text-lamu-700">Open Workspace</span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</section>
