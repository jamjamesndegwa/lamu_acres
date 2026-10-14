@php
    $title = match ($dialog) {
        'reject' => 'Remove from shortlist',
        'visit' => 'Request a site visit',
        'offer' => 'Make an offer',
        default => 'Request documents',
    };
@endphp

<x-crud-page :title="$title" :close="route('buyer.matches.show', $match)">
    @if ($dialog === 'reject')
        <form method="POST" action="{{ route('buyer.matches.reject', $match) }}" data-turbo-frame="_top" data-confirm="Remove this property from your shortlist?" class="space-y-3">
            @csrf
            <label for="buyer_feedback" class="block text-sm font-medium">Rejection feedback</label>
            <textarea id="buyer_feedback" name="buyer_feedback" required minlength="5" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
            <button class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium">Remove from shortlist</button>
        </form>
    @elseif ($dialog === 'visit')
        <form method="POST" action="{{ route('buyer.matches.visits.store', $match) }}" data-turbo-frame="_top" data-confirm="Request this site visit?" class="space-y-3">
            @csrf
            <div>
                <label for="requested_for" class="block text-sm font-medium">Preferred time</label>
                <input id="requested_for" type="datetime-local" name="requested_for" required class="mt-1 rounded-lg border border-sand-200 px-3 py-2">
            </div>
            <div>
                <label for="notes" class="block text-sm font-medium">Notes</label>
                <textarea id="notes" name="notes" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
            </div>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Request visit</button>
        </form>
    @elseif ($dialog === 'offer')
        <form method="POST" action="{{ route('buyer.matches.offers.store', $match) }}" data-turbo-frame="_top" data-confirm="Submit this offer?" class="space-y-3">
            @csrf
            <div>
                <label for="amount" class="block text-sm font-medium">Amount (KSh)</label>
                <input id="amount" name="amount" required class="mt-1 rounded-lg border border-sand-200 px-3 py-2">
            </div>
            <div>
                <label for="message" class="block text-sm font-medium">Message</label>
                <textarea id="message" name="message" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
            </div>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit offer</button>
        </form>
    @else
        <form method="POST" action="{{ route('buyer.matches.documents.store', $match) }}" data-turbo-frame="_top" data-confirm="Request access to the documents?" class="space-y-3">
            @csrf
            <label for="note" class="block text-sm font-medium">What do you need to review?</label>
            <textarea id="note" name="note" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Request documents</button>
        </form>
    @endif
</x-crud-page>
