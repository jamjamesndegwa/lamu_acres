<x-workspace-layout :heading="$referral->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $referral->status->label() }} · {{ $referral->buyer_name }} · {{ $referral->introduced_at?->toFormattedDateString() }}</p>
    @if ($referral->protectsCommission())
        <p class="mt-3 text-stone-700">Commission protection applies under the accepted terms.</p>
    @else
        <p class="mt-3 text-stone-700">Commission protection does not apply. Staff have not accepted this referral.</p>
    @endif
    @if ($messages !== [])
        <ul class="mt-4 max-w-3xl list-disc space-y-1 rounded-2xl border border-sand-200 bg-white px-8 py-4 text-sm">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Contact</dt><dd>{{ $referral->buyer_email }} {{ $referral->buyer_phone }}</dd></div>
        <div><dt class="text-sm text-stone-500">Source</dt><dd>{{ $referral->source }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Proposed terms</dt><dd>{{ $referral->commission_terms }}</dd></div>
        <div class="sm:col-span-2">
            <dt class="text-sm text-stone-500">Consent</dt>
            <dd>
                {{ $referral->consent_note }}
                @if ($referral->privateFile('consent'))
                    <a class="ml-2 font-medium text-lamu-800 hover:underline" href="{{ route('broker.referrals.file', [$referral, 'consent']) }}">Open consent evidence</a>
                @endif
                @if ($referral->privateFile('evidence'))
                    <a class="ml-2 font-medium text-lamu-800 hover:underline" href="{{ route('broker.referrals.file', [$referral, 'evidence']) }}">Open other evidence</a>
                @endif
            </dd>
        </div>
        <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Properties</dt><dd>{{ $referral->properties->pluck('title')->join(', ') }}</dd></div>
    </dl>
    <h2 class="mt-8 font-semibold text-lamu-900">History</h2>
    <ul class="mt-3 space-y-2 text-sm text-stone-700">
        @foreach ($referral->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ $audit->action }} @if ($audit->to_status) to {{ $audit->to_status }} @endif @if ($audit->note) — {{ $audit->note }} @endif</li>
        @endforeach
    </ul>
</x-workspace-layout>
