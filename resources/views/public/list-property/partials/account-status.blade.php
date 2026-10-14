@if ($hasBrokerAccess)
    <p class="mt-6 max-w-2xl rounded-2xl border border-white/25 bg-lamu-800/80 px-4 py-3 text-sm text-sand-100">
        You have broker workspace access.
        <a href="{{ route('broker.listings.create') }}" class="font-medium text-white underline hover:no-underline">Submit a broker listing</a>
        or visit <a href="{{ route('broker-partners') }}" class="font-medium text-white underline hover:no-underline">Broker Partners</a>.
    </p>
@elseif ($hasSellerAccess)
    <p class="mt-6 max-w-2xl rounded-2xl border border-white/25 bg-lamu-800/80 px-4 py-3 text-sm text-sand-100">
        Seller workspace is active.
        <a href="{{ route('seller.properties.create') }}" class="font-medium text-white underline hover:no-underline">Submit a property</a>
        or review <a href="{{ route('account.applications.index') }}" class="font-medium text-white underline hover:no-underline">your applications</a>.
    </p>
@elseif ($sellerApplication || $brokerApplication)
    <p class="mt-6 max-w-2xl rounded-2xl border border-white/25 bg-lamu-800/80 px-4 py-3 text-sm text-sand-100">
        @if ($sellerApplication)
            Seller application: <span class="font-medium text-white">{{ $sellerApplication->status->label() }}</span>.
        @endif
        @if ($brokerApplication)
            @if ($sellerApplication)<span class="mx-1" aria-hidden="true">·</span>@endif
            Broker application: <span class="font-medium text-white">{{ $brokerApplication->status->label() }}</span>.
        @endif
        <a href="{{ route('account.applications.index') }}" class="font-medium text-white underline hover:no-underline">View applications</a>
        @if ($brokerApplication && ! $hasBrokerAccess)
            · <a href="{{ route('broker-partners') }}" class="font-medium text-white underline hover:no-underline">Broker Partners</a>
        @endif
    </p>
@endif

@if ($inProgress->isNotEmpty())
    <div class="mt-6 max-w-xl rounded-2xl border border-white/25 bg-lamu-800/80 px-4 py-4 text-sm text-sand-100">
        <h2 class="font-semibold text-white">In progress</h2>
        <ul class="mt-2 space-y-2">
            @foreach ($inProgress as $property)
                <li>
                    @if ($hasBrokerAccess)
                        <a href="{{ route('broker.listings.show', $property) }}" class="font-medium text-white underline hover:no-underline">{{ $property->title }}</a>
                    @else
                        <a href="{{ route('seller.properties.show', $property) }}" class="font-medium text-white underline hover:no-underline">{{ $property->title }}</a>
                    @endif
                    <span class="text-sand-200">— {{ $property->status->label() }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
