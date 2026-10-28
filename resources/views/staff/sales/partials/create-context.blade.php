<turbo-frame id="sales-create-context">
    @if (! request()->filled('property_id') && ! request()->filled('buyer_id'))
        <p class="rounded-xl border border-dashed border-sand-200 bg-sand-50 px-4 py-3 text-sm text-stone-600">Choose a property and buyer to see linked sellers, offers, enquiries, and open sales cases.</p>
    @else
        <div class="space-y-4">
            @foreach ($context['alerts'] as $alert)
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ $alert }}</p>
            @endforeach

            @if ($context['property'] !== null)
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Property</h2>
                    <p class="mt-1 text-sm">{{ $context['property']['title'] }} · {{ $context['property']['status'] }}</p>
                    <p class="mt-1 text-sm text-stone-600">
                        {{ number_format($context['property']['remaining_acreage'], 4) }} acres remaining
                        · {{ number_format($context['property']['offered_acreage'], 4) }} acres offered on listing
                    </p>
                </article>
            @endif

            @if ($context['sellers'] !== [])
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Seller side</h2>
                    <ul class="mt-2 space-y-1 text-sm text-stone-700">
                        @foreach ($context['sellers'] as $seller)
                            <li>{{ $seller['name'] }} · {{ $seller['relationship'] }} · {{ $seller['status'] }}</li>
                        @endforeach
                    </ul>
                </article>
            @elseif ($context['property'] !== null)
                <p class="text-sm text-stone-600">No verified owners are linked to this property yet.</p>
            @endif

            @if ($context['matching_offers'] !== [])
                <article class="rounded-xl border border-lamu-200 bg-lamu-50/40 p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Offers for this buyer on this property</h2>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($context['matching_offers'] as $offer)
                            <li class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-medium">#{{ $offer['id'] }}</span>
                                <span>KSh {{ number_format($offer['amount']) }}</span>
                                <span class="text-stone-600">{{ $offer['status'] }}</span>
                                @if ($offer['requested_acreage'])
                                    <span class="text-stone-600">{{ $offer['requested_acreage'] }} acres</span>
                                @endif
                                @if ($offer['sales_case'])
                                    <a href="{{ route('staff.sales.show', $offer['sales_transaction_id']) }}" class="font-medium text-lamu-800 hover:underline">{{ $offer['sales_case'] }}</a>
                                @else
                                    <a href="{{ route('staff.offers.show', $offer['id']) }}" class="font-medium text-lamu-800 hover:underline">Review offer</a>
                                    <button type="button" data-apply-offer="{{ $offer['id'] }}" class="rounded-full border border-lamu-800 px-2 py-0.5 text-xs font-medium text-lamu-900">Use for this sale</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif

            @if ($context['buyer_offers'] !== [])
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Other active offers from this buyer</h2>
                    <ul class="mt-2 space-y-1 text-sm text-stone-700">
                        @foreach ($context['buyer_offers'] as $offer)
                            <li>
                                <a href="{{ route('staff.offers.show', $offer['id']) }}" class="font-medium text-lamu-800 hover:underline">#{{ $offer['id'] }}</a>
                                · {{ $offer['property_title'] ?? 'Property' }}
                                · KSh {{ number_format($offer['amount']) }}
                                · {{ $offer['status'] }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif

            @if ($context['property_offers'] !== [])
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Other active offers on this property</h2>
                    <ul class="mt-2 space-y-1 text-sm text-stone-700">
                        @foreach ($context['property_offers'] as $offer)
                            <li>
                                <a href="{{ route('staff.offers.show', $offer['id']) }}" class="font-medium text-lamu-800 hover:underline">#{{ $offer['id'] }}</a>
                                · {{ $offer['buyer_name'] ?? 'Buyer' }}
                                · KSh {{ number_format($offer['amount']) }}
                                · {{ $offer['status'] }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif

            @if ($context['open_transactions'] !== [])
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Open sales cases</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($context['open_transactions'] as $transaction)
                            <li>
                                <a href="{{ route('staff.sales.show', $transaction['id']) }}" class="font-medium text-lamu-800 hover:underline">{{ $transaction['reference'] }}</a>
                                · {{ $transaction['stage'] }}
                                @if ($transaction['buyer'])
                                    · {{ $transaction['buyer'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endif

            @if ($context['linkable_enquiries'] !== [] || $context['linkable_introductions'] !== [] || $context['sale_instructions'] !== [])
                <article class="rounded-xl border border-sand-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-lamu-900">Linkable records</h2>
                    @if ($context['linkable_enquiries'] !== [])
                        <p class="mt-2 text-xs font-medium uppercase tracking-wide text-stone-500">Enquiries without a sales case</p>
                        <ul class="mt-1 space-y-1 text-sm text-stone-700">
                            @foreach ($context['linkable_enquiries'] as $enquiry)
                                <li>Enquiry #{{ $enquiry['id'] }}{{ $enquiry['purchase_request'] ? ' · purchase request' : '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($context['linkable_introductions'] !== [])
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-stone-500">Broker introductions</p>
                        <ul class="mt-1 space-y-1 text-sm text-stone-700">
                            @foreach ($context['linkable_introductions'] as $introduction)
                                <li>{{ $introduction['reference'] }}{{ $introduction['buyer_name'] ? ' · '.$introduction['buyer_name'] : '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($context['sale_instructions'] !== [])
                        <p class="mt-3 text-xs font-medium uppercase tracking-wide text-stone-500">Seller instructions</p>
                        <ul class="mt-1 space-y-1 text-sm text-stone-700">
                            @foreach ($context['sale_instructions'] as $instruction)
                                <li>#{{ $instruction['id'] }}{{ $instruction['seller'] ? ' · '.$instruction['seller'] : '' }} · {{ $instruction['kind'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endif
        </div>
    @endif

    <script type="application/json" data-sales-create-suggestions>@json($context['suggestions'])</script>
</turbo-frame>
