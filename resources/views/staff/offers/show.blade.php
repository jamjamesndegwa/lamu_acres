@php
    $asking = $property->formattedPrice();
    $delta = $property->price_amount !== null ? $offer->amount - (int) $property->price_amount : null;
@endphp

<x-workspace-layout :heading="'Offer on '.$property->title">
    <p class="mt-2 text-sm">
        <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.offers.index') }}">All offers</a>
        ·
        <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', [$property, 'tab' => 'offers']) }}">Property register</a>
        @if ($property->isPubliclyListed() && $property->publicUrl())
            · <a class="font-medium text-lamu-800 hover:underline" href="{{ $property->publicUrl() }}" target="_blank" rel="noopener">Public listing</a>
        @endif
    </p>

    <div class="mt-6 grid max-w-5xl gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-sand-200 bg-white p-5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Offer amount</p>
                    <p class="home-serif mt-1 text-3xl font-semibold text-lamu-900">KSh {{ number_format($offer->amount) }}</p>
                    <p class="mt-2 text-sm text-stone-600">
                        <x-status-badge :label="$offer->status->label()" />
                        · Submitted {{ $offer->created_at?->timezone(config('app.timezone'))->format('j M Y g:i A') }}
                        @if ($offer->requested_acreage !== null)
                            · {{ rtrim(rtrim(number_format((float) $offer->requested_acreage, 4, '.', ''), '0'), '.') }} acres requested
                        @endif
                    </p>
                </div>
                @if ($asking)
                    <div class="rounded-xl bg-sand-50 px-4 py-3 text-sm">
                        <p class="font-medium text-lamu-900">Asking {{ $asking }}</p>
                        @if ($delta !== null)
                            <p class="mt-1 text-stone-600">
                                @if ($delta === 0)
                                    Matches asking price
                                @elseif ($delta > 0)
                                    KSh {{ number_format($delta) }} above asking
                                @else
                                    KSh {{ number_format(abs($delta)) }} below asking
                                @endif
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <h2 class="mt-6 text-sm font-semibold text-lamu-900">Message from the offerer</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $offer->message ?: 'No message was included with this offer.' }}</p>

            <h2 class="mt-6 text-sm font-semibold text-lamu-900">Listed property</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-stone-500">Title</dt>
                    <dd class="font-medium text-lamu-900">{{ $property->title }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Reference</dt>
                    <dd>{{ $property->reference }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Location</dt>
                    <dd>{{ $property->locationLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Purpose</dt>
                    <dd>{{ $property->purpose?->label() ?? '—' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Offerer</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div>
                    <dt class="font-medium text-lamu-900">Name</dt>
                    <dd class="text-stone-700">{{ $buyer->name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-lamu-900">Email</dt>
                    <dd>
                        @if ($buyer->email)
                            <a class="font-medium text-lamu-800 hover:underline" href="mailto:{{ $buyer->email }}">{{ $buyer->email }}</a>
                        @else
                            <span class="text-stone-600">Not recorded</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-lamu-900">Phone</dt>
                    <dd>
                        @if ($buyer->phone)
                            <a class="font-medium text-lamu-800 hover:underline" href="tel:{{ preg_replace('/\s+/', '', $buyer->phone) }}">{{ $buyer->phone }}</a>
                        @else
                            <span class="text-stone-600">Not recorded</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-lamu-900">Account type</dt>
                    <dd class="text-stone-700">
                        @php
                            $roles = collect([
                                $buyer->hasPermission('workspace.buyer') ? 'Buyer' : null,
                                $buyer->hasPermission('workspace.broker') ? 'Broker partner' : null,
                                $buyer->hasPermission('workspace.seller') ? 'Seller' : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        {{ $roles !== '' ? $roles : 'Registered user' }}
                    </dd>
                </div>
                @if ($buyer->buyerProfile)
                    <div>
                        <dt class="font-medium text-lamu-900">Looking for</dt>
                        <dd class="text-stone-700">{{ $buyer->buyerProfile->looking_for ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-lamu-900">Preferred locality</dt>
                        <dd class="text-stone-700">{{ $buyer->buyerProfile->preferred_locality ?: '—' }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    @if ($offer->presentations->isNotEmpty())
        <section class="mt-6 max-w-5xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Presented to seller</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($offer->presentations as $presentation)
                    <li class="rounded-xl border border-sand-200 p-4 text-sm">
                        <p class="font-medium text-lamu-900">KSh {{ number_format($presentation->amount) }}</p>
                        @if ($presentation->summary)
                            <p class="mt-1 text-stone-700">{{ $presentation->summary }}</p>
                        @endif
                        <p class="mt-1 text-stone-600">{{ $presentation->seller_response ?: 'Waiting for seller response' }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @can('updateStatus', $offer)
        <section class="mt-6 max-w-5xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Next steps</h2>
            <p class="mt-1 text-sm text-stone-600">Contact the offerer, then update the offer status or present it to the landowner.</p>

            @if ($offer->submittedByBroker)
                <p class="mt-4 text-sm text-stone-700">Submitted by broker <span class="font-medium text-lamu-900">{{ $offer->submittedByBroker->name }}</span>@if ($offer->brokerIntroduction) · introduction {{ $offer->brokerIntroduction->reference }}@endif.</p>
            @endif

            <div class="mt-4 flex flex-wrap gap-3">
                @if ($offer->salesTransaction)
                    <a href="{{ route('staff.sales.show', $offer->salesTransaction) }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">View sales case</a>
                @else
                    <form method="POST" action="{{ route('staff.offers.sales-case', $offer) }}" data-turbo-frame="_top" data-confirm="Open or link a sales case for this offer?">
                        @csrf
                        <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Open / link sales case</button>
                    </form>
                @endif
                @if ($buyer->email)
                    <a href="mailto:{{ $buyer->email }}?subject={{ rawurlencode('Your offer on '.$property->title) }}" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Email offerer</a>
                @endif
                @if ($buyer->phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $buyer->phone) }}" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Call offerer</a>
                @endif
                <a href="{{ route('staff.properties.show', $property) }}" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Open property record</a>
            </div>

            @php
                $responses = collect($offer->status->transitions())->reject(fn ($status) => $status->value === 'withdrawn');
            @endphp
            @if ($responses->isNotEmpty())
                <form method="POST" action="{{ route('staff.offers.update', $offer) }}" data-turbo-frame="_top" data-confirm="Update this offer status?" class="mt-6 flex flex-wrap items-end gap-3 border-t border-sand-200 pt-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-sm font-medium" for="offer-status">Update status</label>
                        <select id="offer-status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                            @foreach ($responses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save status</button>
                </form>
            @endif

            @if (auth()->user()->hasPermission('staff.sales') || auth()->user()->hasPermission('staff.buyers') || auth()->user()->hasPermission('staff.properties'))
                <form method="POST" action="{{ route('staff.offers.present', $offer) }}" data-turbo-frame="_top" class="mt-6 space-y-3 border-t border-sand-200 pt-5">
                    @csrf
                    <label class="block text-sm font-medium" for="presentation-summary">Present to landowner</label>
                    <textarea id="presentation-summary" name="summary" rows="3" required minlength="5" maxlength="2000" class="w-full rounded-lg border border-sand-200 px-3 py-2 text-sm" placeholder="Summarise this offer for the seller (amount, conditions, your recommendation)."></textarea>
                    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send to seller workspace</button>
                </form>
            @endif
        </section>
    @endcan
</x-workspace-layout>
