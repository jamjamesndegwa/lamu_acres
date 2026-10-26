@php
    $tab = request('tab', 'overview');
    $tabs = [
        'overview' => 'Overview',
        'parties' => 'Buyer & Seller',
        'property' => 'Property',
        'offers' => 'Offers',
        'milestones' => 'Milestones',
        'documents' => 'Documents',
        'closing' => 'Closing',
        'commissions' => 'Commissions',
        'audit' => 'Audit',
    ];
    if (! array_key_exists($tab, $tabs)) {
        $tab = 'overview';
    }
    $showUrl = fn (string $name): string => route('staff.sales.show', $transaction).'?tab='.$name;
@endphp

<x-workspace-layout :heading="$transaction->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $transaction->property->title }} · {{ $transaction->origin->label() }} · {{ $transaction->stage->label() }}</p>
    <p class="mt-1 text-sm">{{ $transaction->buyer?->name ?? 'Buyer not linked' }} · {{ $transaction->agent?->name ?? 'Unassigned' }}</p>

    <nav class="subnav-scroll mt-6 border-b border-sand-200" aria-label="Sale details">
        <div class="flex w-max min-w-full gap-1">
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ $showUrl($key) }}"
                    @class([
                        'whitespace-nowrap border-b-2 px-3 py-3 text-sm',
                        'border-lamu-700 font-medium text-lamu-900' => $tab === $key,
                        'border-transparent text-stone-600 hover:text-lamu-900' => $tab !== $key,
                    ])
                    @if ($tab === $key)
                        aria-current="page"
                    @endif
                >{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    @if ($tab === 'overview')
        <section class="mt-6 grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Stage</h2>
                <p class="mt-2 text-sm">{{ $transaction->stage->label() }}{{ $transaction->outcome ? ' · '.$transaction->outcome->label() : '' }}</p>
                <p class="mt-1 text-sm text-stone-600">Opened {{ $transaction->opened_at?->toFormattedDateString() ?? $transaction->created_at->toFormattedDateString() }}</p>
                @if ($transaction->completed_at)
                    <p class="mt-1 text-sm text-stone-600">Completed {{ $transaction->completed_at->toFormattedDateString() }}</p>
                @endif
            </article>
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Price</h2>
                <p class="mt-2 text-sm">{{ $transaction->agreed_price ? 'KSh '.number_format($transaction->agreed_price) : 'No agreed price' }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $transaction->acreage ? $transaction->acreage.' acres' : 'Acreage not stated' }}</p>
            </article>
            @if ($transaction->enquiry?->purchase_request)
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <h2 class="font-semibold text-lamu-900">Buyer purchase request</h2>
                    <p class="mt-2 text-sm">Proposed price: KSh {{ number_format($transaction->enquiry->requested_price) }}</p>
                    <p class="mt-1 text-sm text-stone-600">Requested area: {{ $transaction->enquiry->requested_acreage ? $transaction->enquiry->requested_acreage.' acres' : 'Full offered area' }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-600">{{ $transaction->enquiry->message }}</p>
                </article>
            @endif
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Origin</h2>
                <p class="mt-2 text-sm">{{ $transaction->origin->label() }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $transaction->agent?->name ?? 'No agent assigned' }}</p>
            </article>
        </section>

        @if ($transaction->isOpen())
            <form method="POST" action="{{ route('staff.sales.update', $transaction) }}" data-turbo-frame="_top" data-confirm="Update this transaction?" class="mt-6 grid max-w-xl gap-2">
                @csrf
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
                <label class="text-sm font-medium text-stone-700">Move stage
                    <select name="stage" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">Keep the current stage</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-stone-700">Outcome
                    <select name="outcome" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">No outcome</option>
                        @foreach ($outcomes as $outcome)
                            <option value="{{ $outcome->value }}">{{ $outcome->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <input name="reason" placeholder="Reason, required for an outcome" class="rounded-lg border border-sand-200 px-3 py-2">
                <input name="agreed_price" placeholder="Agreed price" class="rounded-lg border border-sand-200 px-3 py-2">
                <input name="acreage" placeholder="Agreed acreage" class="rounded-lg border border-sand-200 px-3 py-2">
                <label class="text-sm font-medium text-stone-700">Agent
                    <select name="assigned_to" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">Keep the agent</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save progress</button>
            </form>
        @else
            <p class="mt-6 text-sm text-stone-600">This transaction is closed. Stage and price changes stay off.</p>
        @endif

        <h2 class="mt-8 font-semibold text-lamu-900">Recent timeline</h2>
        <ol class="mt-2 space-y-1 text-sm">
            @forelse ($transaction->timeline->take(8) as $entry)
                <li>{{ $entry->created_at->toDayDateTimeString() }} · {{ $entry->summary }}</li>
            @empty
                <li class="text-stone-600">No timeline entries yet.</li>
            @endforelse
        </ol>
    @endif

    @if ($tab === 'parties')
        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Buyer</h2>
                <p class="mt-2 text-sm">{{ $transaction->buyer?->name ?? 'Buyer not linked' }}</p>
                @if ($transaction->buyer?->email)
                    <p class="mt-1 text-sm text-stone-600">{{ $transaction->buyer->email }}</p>
                @endif
            </article>
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Seller</h2>
                @forelse ($transaction->property->owners as $owner)
                    <p class="mt-2 text-sm">{{ $owner->user?->name ?? 'Unnamed owner' }} · {{ $owner->relationship->label() }} · {{ $owner->status->label() }}</p>
                @empty
                    <p class="mt-2 text-sm text-stone-600">No owner is linked on the property.</p>
                @endforelse
                @if ($transaction->saleInstruction?->seller)
                    <p class="mt-2 text-sm text-stone-600">Sale instruction from {{ $transaction->saleInstruction->seller->name }}</p>
                @endif
            </article>
        </section>
        <article class="mt-4 rounded-2xl border border-sand-200 bg-white p-5 text-sm">
            <h2 class="font-semibold text-lamu-900">Originating record</h2>
            <p class="mt-2">{{ $transaction->origin->label() }}</p>
            @if ($transaction->enquiry)
                <p class="mt-1">Enquiry on {{ $transaction->enquiry->property?->title ?? 'a property' }}</p>
                @if ($transaction->enquiry->purchase_request)
                    <p class="mt-1">Buyer request: KSh {{ number_format($transaction->enquiry->requested_price) }} · {{ $transaction->enquiry->requested_acreage ? $transaction->enquiry->requested_acreage.' acres' : 'full offered area' }}</p>
                @endif
            @endif
            @if ($transaction->introduction)
                <p class="mt-1">Referral {{ $transaction->introduction->reference }} · {{ $transaction->introduction->broker?->name ?? $transaction->introduction->buyer_name }}</p>
            @endif
            @if (! $transaction->enquiry && ! $transaction->introduction && ! $transaction->saleInstruction)
                <p class="mt-1 text-stone-600">No enquiry, referral, or sale instruction is attached.</p>
            @endif
        </article>
    @endif

    @if ($tab === 'property')
        <article class="mt-6 rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">{{ $transaction->property->title }}</h2>
            <p class="mt-2 text-sm text-stone-600">{{ $transaction->property->locationLabel() }}</p>
            @if ($transaction->property->acreage)
                <p class="mt-1 text-sm">{{ $transaction->property->acreage }} acres on the property record</p>
            @endif
            @if ($transaction->acreage)
                <p class="mt-1 text-sm">{{ $transaction->acreage }} acres on this transaction</p>
            @endif
            <a href="{{ route('staff.properties.show', $transaction->property) }}" class="mt-4 inline-block text-sm font-medium text-lamu-800 hover:underline">Open the property register</a>
        </article>
    @endif

    @if ($tab === 'offers')
        @if ($transaction->isOpen())
            <form method="POST" action="{{ route('staff.sales.offers.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Record this offer? Recording an offer does not complete the sale." class="mt-6 grid max-w-xl gap-2">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
                <label class="text-sm font-medium text-stone-700">Offer amount
                    <input name="amount" placeholder="Offer amount" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                </label>
                <label class="text-sm font-medium text-stone-700">Seller decision
                    <select name="seller_response" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">No seller response yet</option>
                        <option value="accept">Accept</option>
                        <option value="decline">Decline</option>
                        <option value="counter">Counter</option>
                    </select>
                </label>
                <textarea name="note" placeholder="Note" class="rounded-lg border border-sand-200 px-3 py-2"></textarea>
                <button class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium">Record offer</button>
            </form>
        @endif
        <h2 class="mt-8 font-semibold text-lamu-900">Offer history</h2>
        <div class="mt-3">
            <x-workspace.table :columns="['Amount', 'Seller decision', 'Recorded', 'Note']">
                @forelse ($transaction->offers as $offer)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 text-sm">KSh {{ number_format($offer->amount) }}</td>
                        <td class="px-4 py-4 text-sm">{{ $offer->seller_response ? 'Seller '.$offer->seller_response : 'Waiting' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $offer->created_at->toFormattedDateString() }}{{ $offer->recorder ? ' · '.$offer->recorder->name : '' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $offer->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No offers" message="Offer history and seller decisions appear here." /></td></tr>
                @endforelse
            </x-workspace.table>
        </div>
        <p class="mt-4 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.offers.index') }}">Open the offers desk</a></p>
    @endif

    @if ($tab === 'milestones')
        <form method="POST" action="{{ route('staff.sales.milestones.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Add this milestone?" class="mt-6 flex flex-wrap gap-2">
            @csrf
            <input name="name" required placeholder="Milestone" class="rounded-lg border border-sand-200 px-3 py-2">
            <input type="date" name="due_on" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Due date">
            <input name="notes" placeholder="Notes" class="rounded-lg border border-sand-200 px-3 py-2">
            <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Add milestone</button>
        </form>
        <div class="mt-6">
            <x-workspace.table :columns="['Milestone', 'Due', 'Recorded']">
                @forelse ($transaction->milestones as $milestone)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 text-sm">{{ $milestone->name }}@if ($milestone->notes) <span class="text-stone-600">· {{ $milestone->notes }}</span>@endif</td>
                        <td class="px-4 py-4 text-sm">{{ $milestone->due_on?->toFormattedDateString() ?? 'No date' }}</td>
                        <td class="px-4 py-4 text-sm">{{ $milestone->created_at->toFormattedDateString() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No milestones" message="Record dated progress as the deal moves." /></td></tr>
                @endforelse
            </x-workspace.table>
        </div>
    @endif

    @if ($tab === 'documents')
        <form method="POST" action="{{ route('staff.sales.documents.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Add this document reference?" class="mt-6 flex flex-wrap gap-2">
            @csrf
            <input name="label" required placeholder="Label" class="rounded-lg border border-sand-200 px-3 py-2">
            <input name="reference" required placeholder="Reference" class="rounded-lg border border-sand-200 px-3 py-2">
            <input name="note" placeholder="Note" class="rounded-lg border border-sand-200 px-3 py-2">
            <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Add document reference</button>
        </form>
        <div class="mt-6">
            <x-workspace.table :columns="['Label', 'Reference', 'Note']">
                @forelse ($transaction->documents as $document)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 text-sm">{{ $document->label }}</td>
                        <td class="px-4 py-4 text-sm">{{ $document->reference }}</td>
                        <td class="px-4 py-4 text-sm">{{ $document->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No documents" message="Document references for this deal appear here." /></td></tr>
                @endforelse
            </x-workspace.table>
        </div>
    @endif

    @if ($tab === 'closing')
        <h2 class="mt-8 font-semibold text-lamu-900">Closing review</h2>
        <form method="POST" action="{{ route('staff.sales.closing', $transaction) }}" data-turbo-frame="_top" data-confirm="Record this closing review?" class="mt-3 grid max-w-xl gap-2">
            @csrf
            <select name="decision" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="authorized">Authorize completion</option>
                <option value="refused">Refuse completion</option>
            </select>
            <textarea name="reason" required minlength="8" class="rounded-lg border border-sand-200 px-3 py-2" placeholder="Reason"></textarea>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save closing review</button>
        </form>
        <ol class="mt-4 space-y-2 text-sm">
            @forelse ($transaction->closingReviews as $review)
                <li class="rounded-2xl border border-sand-200 bg-white p-4">{{ $review->created_at->toDayDateTimeString() }} · {{ $review->reviewer?->name }} · {{ $review->decision }} · {{ $review->reason }}</li>
            @empty
                <li class="text-stone-600">No closing assessment has been recorded.</li>
            @endforelse
        </ol>

        <h2 class="mt-8 font-semibold text-lamu-900">Closing evidence</h2>
        @foreach (\App\Services\Sales\ClosingDesk::EVIDENCE as $code => $label)
            @php $current = $transaction->evidence->firstWhere('code', $code); @endphp
            <form method="POST" action="{{ route('staff.sales.evidence', $transaction) }}" data-turbo-frame="_top" data-confirm="Update this closing document?" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                @csrf
                <input type="hidden" name="code" value="{{ $code }}">
                <span class="w-40">{{ $label }}</span>
                <span class="text-stone-500">{{ $current?->state?->label() ?? 'Not recorded' }}</span>
                <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @foreach (\App\Enums\ClosingEvidenceState::cases() as $state)
                        <option value="{{ $state->value }}" @selected($current?->state === $state)>{{ $state->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-full border border-sand-200 px-4 py-2">Save</button>
            </form>
        @endforeach

        <h2 class="mt-8 font-semibold text-lamu-900">Complete transaction</h2>
        @if ($transaction->stage === \App\Enums\SalesStage::CompletionPending)
            <p class="mt-2 max-w-2xl text-sm text-stone-600">Completion is restricted to the sales desk, and only while the deal is in the completion queue. Confirming it updates inventory.</p>
            <form method="POST" action="{{ route('staff.sales.complete', $transaction) }}" data-turbo-frame="_top" data-confirm="Complete {{ $transaction->reference }} and update inventory? Resulting state: Completed." class="mt-4">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
                <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Confirm completion</button>
            </form>
        @else
            <p class="mt-2 text-sm text-stone-600">The complete action stays hidden until the stage is Completion Pending.</p>
        @endif
        <p class="mt-4 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.sales.completion') }}">Open the completion queue</a></p>
    @endif

    @if ($tab === 'commissions')
        <h2 class="mt-8 font-semibold text-lamu-900">Commission agreements</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse ($transaction->agreements as $agreement)
                <li><a class="text-lamu-800 hover:underline" href="{{ route('staff.commissions.show', $agreement) }}">{{ $agreement->reference }}</a> · {{ $agreement->status->label() }} · {{ $agreement->broker->name }}</li>
            @empty
                <li class="text-stone-600">No commission has been proposed. Registering a buyer does not earn commission.</li>
            @endforelse
        </ul>
        @if ($canProposeCommission)
            <form method="POST" action="{{ route('staff.commissions.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Propose this commission agreement? Proposing it does not earn or pay commission." class="mt-3 grid max-w-xl gap-2">
                @csrf
                <label class="text-sm font-medium text-stone-700">Introducing broker
                    <select name="broker_id" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                        <option value="">Select a broker</option>
                        @foreach ($brokers as $broker)
                            <option value="{{ $broker->id }}">{{ $broker->name }}</option>
                        @endforeach
                    </select>
                </label>
                <input name="rate_percent" placeholder="Rate percent" class="rounded-lg border border-sand-200 px-3 py-2">
                <input name="fixed_amount" placeholder="Or a fixed amount" class="rounded-lg border border-sand-200 px-3 py-2">
                <select name="funding_party" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="seller">Seller funds it</option>
                    <option value="buyer">Buyer funds it</option>
                    <option value="brokerage">Brokerage funds it</option>
                </select>
                <input type="date" name="protection_ends_at" required class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Protection end date">
                <input name="payment_due_days" value="14" class="rounded-lg border border-sand-200 px-3 py-2" aria-label="Payment due in days">
                <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Propose agreement</button>
            </form>
        @else
            <p class="mt-3 text-sm text-stone-600">Proposing a commission requires the propose-commission permission.</p>
        @endif
    @endif

    @if ($tab === 'audit')
        <div class="mt-6">
            <x-workspace.table :columns="['When', 'Event', 'Actor']">
                @forelse ($transaction->audits as $audit)
                    <tr class="border-b border-sand-200 last:border-b-0">
                        <td class="px-4 py-4 text-sm">{{ $audit->created_at->toDayDateTimeString() }}</td>
                        <td class="px-4 py-4 text-sm">{{ $audit->event }}</td>
                        <td class="px-4 py-4 text-sm">{{ $audit->actor?->name ?? 'System' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No audit events" message="Deal changes are listed here as they are recorded." /></td></tr>
                @endforelse
            </x-workspace.table>
        </div>
        <h2 class="mt-8 font-semibold text-lamu-900">Timeline</h2>
        <x-activity-timeline :items="$transaction->timeline->map(fn ($entry) => [
            'when' => $entry->created_at->toDayDateTimeString(),
            'text' => $entry->summary,
        ])->all()" />
    @endif
</x-workspace-layout>
