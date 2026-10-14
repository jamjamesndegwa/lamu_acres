<x-workspace-layout :heading="$transaction->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $transaction->property->title }} · {{ $transaction->origin->label() }} · {{ $transaction->stage->label() }}</p>
    <p class="mt-1 text-sm">{{ $transaction->buyer?->name ?? 'Buyer not linked' }} · {{ $transaction->agent?->name ?? 'Unassigned' }}</p>
    @if ($transaction->agreed_price)
        <p class="mt-1 text-sm">Agreed price KSh {{ number_format($transaction->agreed_price) }} · {{ $transaction->acreage }} acres</p>
    @endif

    @if ($transaction->isOpen())
        <form method="POST" action="{{ route('staff.sales.update', $transaction) }}" data-turbo-frame="_top" data-confirm="Update this transaction?" class="mt-6 grid max-w-xl gap-2">
            @csrf
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
            <select name="stage" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Keep the current stage</option>
                @foreach ($stages as $stage)
                    <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                @endforeach
            </select>
            <select name="outcome" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">No outcome</option>
                @foreach ($outcomes as $outcome)
                    <option value="{{ $outcome->value }}">{{ $outcome->label() }}</option>
                @endforeach
            </select>
            <input name="reason" placeholder="Reason, required for an outcome" class="rounded-lg border border-sand-200 px-3 py-2">
            <input name="agreed_price" placeholder="Agreed price" class="rounded-lg border border-sand-200 px-3 py-2">
            <select name="assigned_to" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Keep the agent</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </select>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save progress</button>
        </form>
        <form method="POST" action="{{ route('staff.sales.offers.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Record this offer?" class="mt-4 grid max-w-xl gap-2">
            @csrf
            <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
            <input name="amount" placeholder="Offer amount" required class="rounded-lg border border-sand-200 px-3 py-2">
            <select name="seller_response" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">No seller response yet</option>
                <option value="accept">Accept</option>
                <option value="decline">Decline</option>
                <option value="counter">Counter</option>
            </select>
            <button class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium">Record offer</button>
        </form>
    @endif

    <h2 class="mt-8 font-semibold text-lamu-900">Offer history</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @foreach ($transaction->offers as $offer)
            <li>KSh {{ number_format($offer->amount) }} {{ $offer->seller_response ? '· seller '.$offer->seller_response : '' }}</li>
        @endforeach
    </ul>

    <h2 class="mt-8 font-semibold text-lamu-900">Timeline</h2>
    <ol class="mt-2 space-y-1 text-sm">
        @foreach ($transaction->timeline as $entry)
            <li>{{ $entry->created_at->toDayDateTimeString() }} · {{ $entry->summary }}</li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('staff.sales.milestones.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Add this milestone?" class="mt-6 flex flex-wrap gap-2">
        @csrf
        <input name="name" required placeholder="Milestone" class="rounded-lg border border-sand-200 px-3 py-2">
        <input type="date" name="due_on" class="rounded-lg border border-sand-200 px-3 py-2">
        <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Add milestone</button>
    </form>
    <form method="POST" action="{{ route('staff.sales.documents.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Add this document reference?" class="mt-3 flex flex-wrap gap-2">
        @csrf
        <input name="label" required placeholder="Label" class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="reference" required placeholder="Reference" class="rounded-lg border border-sand-200 px-3 py-2">
        <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Add document reference</button>
    </form>

    <h2 class="mt-8 font-semibold text-lamu-900">Commission agreements</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @forelse ($transaction->agreements as $agreement)
            <li><a class="text-lamu-800 hover:underline" href="{{ route('staff.commissions.show', $agreement) }}">{{ $agreement->reference }}</a> · {{ $agreement->status->label() }}</li>
        @empty
            <li class="text-stone-600">No commission has been proposed. Registering a buyer does not earn commission.</li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route('staff.commissions.store', $transaction) }}" data-turbo-frame="_top" data-confirm="Propose this commission agreement?" class="mt-3 grid max-w-xl gap-2">
        @csrf
        <input name="broker_id" required placeholder="Introducing broker user id" class="rounded-lg border border-sand-200 px-3 py-2">
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

    <h2 class="mt-8 font-semibold text-lamu-900">Closing</h2>
    <form method="POST" action="{{ route('staff.sales.closing', $transaction) }}" data-turbo-frame="_top" data-confirm="Record this closing review?" class="mt-3 grid max-w-xl gap-2">
        @csrf
        <select name="decision" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="authorized">Authorize completion</option>
            <option value="refused">Refuse completion</option>
        </select>
        <textarea name="reason" required minlength="8" class="rounded-lg border border-sand-200 px-3 py-2" placeholder="Reason"></textarea>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save closing review</button>
    </form>
    @foreach (\App\Services\Sales\ClosingDesk::EVIDENCE as $code => $label)
        <form method="POST" action="{{ route('staff.sales.evidence', $transaction) }}" data-turbo-frame="_top" data-confirm="Update this closing document?" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            @csrf
            <input type="hidden" name="code" value="{{ $code }}">
            <span class="w-40">{{ $label }}</span>
            <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                @foreach (\App\Enums\ClosingEvidenceState::cases() as $state)
                    <option value="{{ $state->value }}">{{ $state->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-full border border-sand-200 px-4 py-2">Save</button>
        </form>
    @endforeach
    @if ($transaction->stage === \App\Enums\SalesStage::CompletionPending)
        <form method="POST" action="{{ route('staff.sales.complete', $transaction) }}" data-turbo-frame="_top" data-confirm="Complete this sale and update inventory?" class="mt-4">
            @csrf
            <input type="hidden" name="lock_version" value="{{ $transaction->lock_version }}">
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Confirm completion</button>
        </form>
    @endif
</x-workspace-layout>
