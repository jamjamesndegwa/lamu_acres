<x-workspace-layout :heading="$agreement->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $agreement->status->label() }} · {{ $agreement->property?->title }}</p>
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Recorded rate</dt><dd>{{ $agreement->rate_percent !== null ? $agreement->rate_percent.'%' : 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Recorded fixed amount</dt><dd>{{ $agreement->fixed_amount !== null ? 'KSh '.number_format($agreement->fixed_amount) : 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Recorded earned amount</dt><dd>{{ $agreement->earned_amount !== null ? 'KSh '.number_format($agreement->earned_amount) : 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Funding</dt><dd>{{ $agreement->funding_party?->label() ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Qualifying event</dt><dd>{{ $agreement->qualifying_event?->label() ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Payment due</dt><dd>{{ $agreement->payment_due_days !== null ? $agreement->payment_due_days.' days' : 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Protection starts</dt><dd>{{ $agreement->protection_starts_at?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Protection ends</dt><dd>{{ $agreement->protection_ends_at?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Accepted</dt><dd>{{ $agreement->accepted_at?->toFormattedDateString() ?: 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Deal</dt><dd>{{ $agreement->transaction?->reference }} · {{ $agreement->transaction?->stage?->label() }}</dd></div>
    </dl>
    @if ($agreement->dispute_reason)
        <p class="mt-4 max-w-3xl text-sm text-stone-700">Dispute: {{ $agreement->dispute_reason }}</p>
    @endif
    @if ($agreement->payment)
        <p class="mt-4 text-sm">Payment {{ $agreement->payment->reference }} · {{ $agreement->payment->status }} · KSh {{ number_format($agreement->payment->amount) }}. No statement file is attached to this payment.</p>
    @endif
</x-workspace-layout>
