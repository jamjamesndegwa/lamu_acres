<x-workspace-layout :heading="$property->title">
    <p class="mt-2 text-sm text-stone-600">{{ $property->reference }} · {{ $property->status->label() }} · {{ $property->locationLabel() }}</p>
    @include('staff.properties.partials.tabs')

    @include('staff.properties.partials.approval-guide')

    @if ($tab === 'overview')
        <div class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('staff.properties.edit', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Edit details</a>
            <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'status']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Change status</a>
            <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'portion']) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Partial sale</a>
        </div>
        <h2 id="listing-details" class="mt-8 text-lg font-semibold text-lamu-900">Listing details</h2>
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
        <div class="mt-6 max-w-4xl">
            <x-map-viewer :latitude="$property->public_latitude" :longitude="$property->public_longitude" empty="No map point has been recorded for this property." />
        </div>
        @if ($property->summary)
            <p class="mt-4 max-w-3xl font-medium text-stone-800">{{ $property->summary }}</p>
        @endif
        @if ($property->description)
            <p class="mt-4 max-w-3xl text-stone-700">{{ $property->description }}</p>
        @endif
        @include('staff.properties.partials.listing-details')
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
        <h2 class="mt-6 text-lg font-semibold text-lamu-900">Ownership and seller documents</h2>
        <p class="mt-1 max-w-3xl text-sm text-stone-600">Review the uploaded files below, then confirm the owner's relationship to the property. These are separate decisions.</p>
        <ul class="mt-6 max-w-3xl space-y-3">
            @forelse ($property->owners as $owner)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p class="font-medium text-lamu-900">{{ $owner->user?->name ?? 'Unlinked account' }}</p>
                    @php($ownerIdentityApplication = $owner->user?->capabilityApplications()->where('capability', \App\Enums\PartnerCapability::Seller)->first())
                    <p class="mt-1 text-sm text-stone-600">Onboarding legal name: {{ $ownerIdentityApplication?->verification['legal_name'] ?? 'Not recorded' }} · Identity: {{ $ownerIdentityApplication?->verification['identity']['outcome'] ?? 'Not verified' }}</p>

                    <p class="text-stone-600">{{ $owner->relationship->label() }} · {{ $owner->status->label() }} @if ($owner->share_percent !== null) · {{ $owner->share_percent }}% @endif</p>
                    @if ($owner->status !== \App\Enums\OwnerLinkStatus::Verified || app(\App\Services\Seller\SellerPortal::class)->ownerIdentityBlockers($owner) !== [])
                        <form method="POST" action="{{ route('staff.owners.verify', $owner) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="flex w-full items-start gap-2 text-sm"><input type="checkbox" name="identity_consistency_confirmed" value="1" required>I matched the current verified applicant identity, ownership evidence and representative authority to this owner record.</label>
                            <label class="block w-full text-sm">Review reason (do not include identity numbers)<input name="reason" required minlength="8" maxlength="1000" class="mt-1 block w-full rounded-lg border border-sand-200 px-3 py-2"></label>
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
        @include('staff.properties.partials.ownership-documents')
    @elseif ($tab === 'verification')
        <div class="mt-6 rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Record verification decisions</h2>
            <p class="mt-1 text-sm text-stone-600">Use the verification desk to review each critical check, record evidence and clear exceptions. Approving the seller's files alone does not complete the passport.</p>
            @if (auth()->user()->hasPermission('staff.verification'))
                <a href="{{ route('staff.verification.show', $property) }}" class="mt-3 inline-flex rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Open verification desk</a>
            @else
                <p class="mt-3 text-sm text-stone-600">Ask verification staff to complete these decisions.</p>
            @endif
        </div>
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
        <x-document-checklist
            empty="No verification checklist has been opened."
            :items="$property->verificationItems->map(fn ($item) => [
                'label' => $item->category->label(),
                'state' => $item->state->label(),
                'note' => $item->reviewer?->name,
            ])->all()"
        />
        <h2 class="mt-8 font-semibold text-lamu-900">Document checks</h2>
        <x-document-checklist
            empty="Title search and survey plan have not been recorded. They are saved with the marketing attributes."
            :items="$property->documentChecks->map(fn ($check) => [
                'label' => $check->label(),
                'state' => $check->state->label(),
            ])->all()"
        />
        @if (auth()->user()->hasPermission('staff.verification'))
            <a href="{{ route('staff.verification.show', $property) }}" class="mt-4 inline-block text-sm font-medium text-lamu-800">Open the verification desk</a>
        @endif
        @include('staff.properties.partials.ownership-documents')
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
                    <p class="font-medium">{{ $authority->seller?->name ?? 'Seller' }} · {{ $authority->status->label() }} · {{ $authority->scope?->label() ?? 'Sale and lease' }}</p>
                    <p class="text-stone-600">{{ $authority->valid_until ? 'Valid until '.$authority->valid_until->format('j M Y') : 'No expiry stated' }}@if ($authority->isExpired()) · Expired @endif</p>
                    @if ($authority->terms)
                        <p class="mt-2 text-stone-700">{{ $authority->terms }}</p>
                    @endif
                    <form method="POST" action="{{ route('staff.authorities.update', $authority) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        @include('admin.approvals.return-field')
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
        <h2 class="mt-8 font-semibold text-lamu-900">Seller instructions</h2>
        <p class="mt-1 text-sm text-stone-600">Price, availability and withdrawal requests. Apply approved changes through Edit details or Change status.</p>
        <ul class="mt-3 max-w-3xl space-y-3">
            @forelse ($property->saleInstructions as $instruction)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p class="font-medium">{{ $instruction->kind->label() }} · {{ $instruction->seller?->name ?? 'Seller' }} · {{ $instruction->created_at?->format('j M Y') }}</p>
                    <p class="mt-1 text-stone-700">{{ $instruction->body }}</p>
                    @if ($instruction->requested_availability)
                        <p class="mt-1 text-stone-600">Requested availability: {{ $instruction->requested_availability }}</p>
                    @endif
                </li>
            @empty
                <li class="text-sm text-stone-600">No seller instruction has been recorded.</li>
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
                <li>{{ $transaction->reference }} · {{ $transaction->kind?->label() ?? 'Sale' }} · {{ $transaction->status->label() }} @if ($transaction->amount !== null) · KSh {{ number_format($transaction->amount) }} @endif</li>
            @empty
                <li class="text-stone-600">No transaction statement has been recorded.</li>
            @endforelse
        </ul>
        @if ($property->isLease())
            @php($canLease = auth()->user()->hasPermission('staff.properties') || auth()->user()->hasPermission('staff.sales'))
            <h2 class="mt-8 font-semibold text-lamu-900">Leases</h2>
            <ul class="mt-3 max-w-3xl space-y-3">
                @forelse ($property->transactions->filter(fn ($transaction) => $transaction->isLease()) as $lease)
                    <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                        <p class="font-medium text-lamu-900">{{ $lease->reference }} · {{ $lease->ended_at ? 'Ended '.$lease->ended_at->format('j M Y') : 'Active' }}</p>
                        <p class="text-stone-600">{{ $lease->starts_on?->format('j M Y') }} to {{ $lease->ends_on?->format('j M Y') ?? 'open end' }} · {{ number_format((float) $lease->acreage, 2) }} acres · KSh {{ number_format((int) $lease->amount) }} agreed rent · confirmed by {{ $lease->confirmer?->name ?? 'staff' }}</p>
                        @if ($lease->ends_on && ! $lease->ended_at && $lease->ends_on->lte(now()->addDays(60)))
                            <p class="mt-1 text-amber-800">Lease {{ $lease->ends_on->isPast() ? 'end date has passed' : 'ends soon' }}. Confirm the position before re-advertising.</p>
                        @endif
                        @if ($lease->terms)
                            <p class="mt-1 text-stone-700">{{ $lease->terms }}</p>
                        @endif
                        @if ($canLease && $lease->isActiveLease())
                            <form method="POST" action="{{ route('staff.leases.end', $lease) }}" data-turbo-frame="_top" data-confirm="Record that this lease has ended? The land returns to review before it is advertised again." class="mt-3 flex flex-wrap items-end gap-2">
                                @csrf
                                @method('PUT')
                                <label class="text-sm">Reason
                                    <input name="reason" required minlength="8" class="mt-1 block w-72 rounded-lg border border-sand-200 px-3 py-2">
                                </label>
                                <button class="rounded-full border border-lamu-900 px-4 py-2 text-sm">End lease</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-stone-600">No lease has been recorded.</li>
                @endforelse
            </ul>
            @if ($canLease && in_array(\App\Enums\PropertyStatus::Leased, $property->allowedTransitions(), true))
                <form method="POST" action="{{ route('staff.properties.leases.store', $property) }}" data-turbo-frame="_top" data-confirm="Record this confirmed lease? The listing will stop being advertised." class="mt-6 grid max-w-3xl gap-3 sm:grid-cols-2">
                    @csrf
                    <h3 class="font-medium text-lamu-900 sm:col-span-2">Record a confirmed lease</h3>
                    <label class="block text-sm">Commencement date
                        <input type="date" name="starts_on" required value="{{ old('starts_on') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    </label>
                    <label class="block text-sm">Agreed end date
                        <input type="date" name="ends_on" value="{{ old('ends_on') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    </label>
                    <x-measure-field name="acreage" label="Leased area" unit="acres" :value="old('acreage', $property->offeredAcreage())" required />
                    <x-measure-field name="amount" label="Agreed rent" unit="KSh" :value="old('amount', $property->price_amount)" inputmode="numeric" required />
                    <label class="block text-sm sm:col-span-2">Term and renewal information
                        <textarea name="terms" rows="2" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('terms') }}</textarea>
                    </label>
                    @if ($errors->hasAny(['lease', 'acreage', 'starts_on', 'ends_on', 'amount']))
                        <p class="text-sm text-red-700 sm:col-span-2" role="alert">{{ $errors->first() }}</p>
                    @endif
                    <p class="text-sm text-stone-600 sm:col-span-2">Only record a lease once it has been executed and confirmed. Registration and legal completion remain with the legal team.</p>
                    <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white sm:col-span-2 sm:justify-self-start">Record lease</button>
                </form>
            @endif
        @endif
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
        <x-activity-timeline
            empty="No change has been recorded."
            :items="$property->audits->map(fn ($audit) => [
                'when' => $audit->created_at?->format('j M Y H:i'),
                'text' => trim(($audit->actor?->name ?? 'System').' · '.$audit->action.(($audit->from_status || $audit->to_status) ? ' · '.$audit->from_status.' → '.$audit->to_status : '')),
            ])->all()"
        />
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
