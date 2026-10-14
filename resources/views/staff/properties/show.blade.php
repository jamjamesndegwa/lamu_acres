<x-workspace-layout :heading="$property->title">
    <p class="mt-2 text-sm text-stone-600">{{ $property->reference }} · {{ $property->status->label() }} · {{ $property->locationLabel() }}</p>
    @include('staff.properties.partials.tabs')

    @if ($tab === 'overview')
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('staff.properties.edit', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Edit details</a>
            <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'status']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Change status</a>
            <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'portion']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Partial sale</a>
        </div>
        <dl class="mt-6 grid max-w-4xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
            <div><dt class="text-sm text-stone-500">Type</dt><dd>{{ $property->property_type?->label() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Land use</dt><dd>{{ $property->land_use?->label() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Purpose</dt><dd>{{ $property->purpose?->label() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Availability</dt><dd>{{ $property->availability?->label() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Acreage</dt><dd>{{ number_format((float) $property->acreage, 2) }}</dd></div>
            <div><dt class="text-sm text-stone-500">Remaining</dt><dd>{{ number_format($property->remainingAcreage(), 2) }} acres</dd></div>
            <div><dt class="text-sm text-stone-500">Asking price</dt><dd>{{ $property->formattedPrice() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Price per acre</dt><dd>{{ $property->pricePerAcre() ? 'KSh '.number_format($property->pricePerAcre()) : 'Not recorded' }}</dd></div>
        </dl>
        @if ($property->description)
            <p class="mt-4 max-w-3xl text-stone-700">{{ $property->description }}</p>
        @endif
        @if ($property->parent)
            <p class="mt-4 text-sm">Portion of <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', $property->parent) }}">{{ $property->parent->reference }}</a>@if ($property->portion_label) · {{ $property->portion_label }}@endif</p>
        @endif
        <h2 class="mt-8 font-semibold text-lamu-900">Portions</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($property->portions as $portion)
                <li><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', $portion) }}">{{ $portion->portion_label ?: $portion->title }}</a> · {{ number_format((float) $portion->acreage, 2) }} acres · {{ $portion->status->label() }}</li>
            @empty
                <li class="text-stone-600">No portion has been split from this record.</li>
            @endforelse
        </ul>
    @elseif ($tab === 'owners')
        <ul class="mt-6 max-w-3xl space-y-3">
            @forelse ($property->owners as $owner)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p class="font-medium text-lamu-900">{{ $owner->user?->name ?? 'Unlinked account' }}</p>
                    <p class="text-stone-600">{{ $owner->relationship->label() }} · {{ $owner->status->label() }} @if ($owner->share_percent !== null) · {{ $owner->share_percent }}% @endif</p>
                    @if ($owner->status !== \App\Enums\OwnerLinkStatus::Verified)
                        <form method="POST" action="{{ route('staff.owners.verify', $owner) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="text-sm">Share percent
                                <input name="share_percent" value="{{ $owner->share_percent }}" class="mt-1 block w-28 rounded-lg border border-sand-200 px-3 py-2">
                            </label>
                            <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Verify link</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-sm text-stone-600">No owner is linked to this property.</li>
            @endforelse
        </ul>
        <h2 class="mt-8 font-semibold text-lamu-900">Link an existing seller</h2>
        <form method="POST" action="{{ route('staff.properties.owners.store', $property) }}" class="mt-3 flex max-w-3xl flex-wrap items-end gap-3">
            @csrf
            <label class="text-sm">Seller account id
                <input name="user_id" required class="mt-1 block w-32 rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="text-sm">Relationship
                <select name="relationship" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
                    @foreach (\App\Enums\OwnerRelationship::cases() as $relationship)
                        <option value="{{ $relationship->value }}">{{ $relationship->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Link owner</button>
        </form>
        <p class="mt-2 text-sm text-stone-600">The account must already be a seller. This does not create a new user.</p>
    @elseif ($tab === 'verification')
        <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
            <div><dt class="text-sm text-stone-500">Public review level</dt><dd>{{ $property->verification_level?->label() ?? 'Not reviewed' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Last review</dt><dd>{{ $property->last_reviewed_at?->format('j M Y') ?? 'No review date recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Passport</dt><dd>{{ $property->passport?->level?->label() ?? 'No passport recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Override</dt><dd>{{ $property->passport?->override?->label() ?? 'None' }}</dd></div>
        </dl>
        @if ($property->passport?->critical_exception)
            <p class="mt-3 text-sm text-stone-700">Critical exception: {{ $property->passport->critical_exception }}</p>
        @endif
        <h2 class="mt-8 font-semibold text-lamu-900">Checklist</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($property->verificationItems as $item)
                <li>{{ $item->category->label() }} · {{ $item->state->label() }} @if ($item->reviewer) · {{ $item->reviewer->name }} @endif</li>
            @empty
                <li class="text-stone-600">No verification checklist has been opened.</li>
            @endforelse
        </ul>
        <h2 class="mt-8 font-semibold text-lamu-900">Document checks</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($property->documentChecks as $check)
                <li>{{ $check->label() }} · {{ $check->state->label() }}</li>
            @empty
                <li class="text-stone-600">Title search and survey plan have not been recorded. They are saved with the marketing attributes.</li>
            @endforelse
        </ul>
        @if (auth()->user()->hasPermission('staff.verification'))
            <a href="{{ route('staff.verification.show', $property) }}" class="mt-4 inline-block text-sm font-medium text-lamu-800">Open the verification desk</a>
        @endif
    @elseif ($tab === 'marketing')
        <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
            <div><dt class="text-sm text-stone-500">Marketing role</dt><dd>{{ $property->marketing_role?->label() ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Representative</dt><dd>{{ $property->representative?->name ?? 'Not assigned' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Featured</dt><dd>{{ $property->featured ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-sm text-stone-500">Public page</dt><dd>@if ($property->isPublished())<a class="text-lamu-800 hover:underline" href="{{ $property->publicUrl() }}">View listing</a>@else Not published @endif</dd></div>
        </dl>
        @if ($property->submitter)
            <p class="mt-4 text-sm">Submitted by {{ $property->submitter->name }}. <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.listings.show', $property) }}">Open listing review</a></p>
        @endif
        <h2 class="mt-8 font-semibold text-lamu-900">Marketing authority</h2>
        <ul class="mt-3 max-w-3xl space-y-3">
            @forelse ($property->authorities as $authority)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p class="font-medium">{{ $authority->seller?->name ?? 'Seller' }} · {{ $authority->status->label() }}</p>
                    @if ($authority->terms)
                        <p class="mt-2 text-stone-700">{{ $authority->terms }}</p>
                    @endif
                    <form method="POST" action="{{ route('staff.authorities.update', $authority) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        @method('PUT')
                        <select name="status" class="rounded-lg border border-sand-200 px-3 py-2">
                            @foreach (['active' => 'Active', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'] as $value => $label)
                                <option value="{{ $value }}" @selected($authority->status->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">Save decision</button>
                    </form>
                </li>
            @empty
                <li class="text-sm text-stone-600">No marketing authority has been submitted.</li>
            @endforelse
        </ul>
        @include('staff.properties.partials.attributes')
    @elseif ($tab === 'enquiries')
        <ul class="mt-6 max-w-3xl space-y-3">
            @forelse ($property->enquiries as $enquiry)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p class="font-medium text-lamu-900">{{ $enquiry->buyer?->name ?? 'Buyer' }} · {{ $enquiry->status->label() }}</p>
                    <p class="mt-2 text-stone-700">{{ \Illuminate\Support\Str::limit((string) $enquiry->message, 240) }}</p>
                    @can('view', $enquiry)
                        <a class="mt-2 inline-block font-medium text-lamu-800 hover:underline" href="{{ route('staff.enquiries.show', $enquiry) }}">Open enquiry</a>
                    @endcan
                </li>
            @empty
                <li class="text-sm text-stone-600">No enquiry has been recorded against this property.</li>
            @endforelse
        </ul>
    @elseif ($tab === 'transactions')
        <h2 class="mt-6 font-semibold text-lamu-900">Statements</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($property->transactions as $transaction)
                <li>{{ $transaction->reference }} · {{ $transaction->status->label() }} @if ($transaction->amount !== null) · KSh {{ number_format($transaction->amount) }} @endif</li>
            @empty
                <li class="text-stone-600">No transaction statement has been recorded.</li>
            @endforelse
        </ul>
        <h2 class="mt-8 font-semibold text-lamu-900">Sales</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($property->salesTransactions as $sale)
                <li>
                    @if (auth()->user()->hasPermission('staff.sales'))
                        <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.sales.show', $sale) }}">{{ $sale->reference }}</a>
                    @else
                        {{ $sale->reference }}
                    @endif
                    · {{ $sale->stage->label() }}
                </li>
            @empty
                <li class="text-stone-600">No sales file is open for this property.</li>
            @endforelse
        </ul>
        @if (auth()->user()->hasPermission('staff.sales'))
            <form method="POST" action="{{ route('staff.properties.transactions.store', $property) }}" class="mt-6 max-w-xl space-y-3">
                @csrf
                <label class="block text-sm">Status
                    <select name="status" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        @foreach (\App\Enums\TransactionStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">Amount (KSh)
                    <input name="amount" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                </label>
                <label class="block text-sm">Statement
                    <textarea name="statement" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
                </label>
                <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Record statement</button>
            </form>
        @else
            <p class="mt-4 text-sm text-stone-600">A sales desk records transaction statements.</p>
        @endif
    @else
        <h2 class="mt-6 font-semibold text-lamu-900">Changes</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($property->audits as $audit)
                <li>{{ $audit->created_at?->format('j M Y H:i') }} · {{ $audit->actor?->name ?? 'System' }} · {{ $audit->action }} @if ($audit->from_status || $audit->to_status) · {{ $audit->from_status }} → {{ $audit->to_status }} @endif</li>
            @empty
                <li class="text-stone-600">No change has been recorded.</li>
            @endforelse
        </ul>
        <h2 class="mt-8 font-semibold text-lamu-900">Price record</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($property->priceChanges as $change)
                <li>KSh {{ number_format($change->price_amount) }} · {{ $change->created_at?->format('j M Y H:i') }}</li>
            @empty
                <li class="text-stone-600">No price change has been recorded.</li>
            @endforelse
        </ul>
        <h2 class="mt-8 font-semibold text-lamu-900">Availability record</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($property->availabilityEvents as $event)
                <li>{{ $event->status }} · {{ $event->availability }} · {{ $event->created_at?->format('j M Y H:i') }}</li>
            @empty
                <li class="text-stone-600">No availability change has been recorded.</li>
            @endforelse
        </ul>
    @endif
</x-workspace-layout>
