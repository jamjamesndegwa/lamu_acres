<x-workspace-layout :heading="$referral->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $referral->broker?->name }} introduced {{ $referral->buyer_name }} · {{ $referral->status->label() }}</p>
    @if ($referral->conflicts)
        <div class="mt-4 max-w-3xl rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Possible conflicts</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($referral->conflicts as $conflict)
                    <li>
                        {{ str_replace('_', ' ', $conflict['type']) }}
                        @if (! empty($conflict['reference'])) · {{ $conflict['reference'] }} @endif
                        @if (! empty($conflict['broker_name'])) · {{ $conflict['broker_name'] }} @endif
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-stone-600">Do not treat the earliest submission as the protected one. Choose accepted, rejected, or disputed.</p>
        </div>
    @endif
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 text-sm sm:grid-cols-2">
        <div><dt class="text-stone-500">Contact</dt><dd>{{ $referral->buyer_email }} {{ $referral->buyer_phone }}</dd></div>
        <div><dt class="text-stone-500">Source</dt><dd>{{ $referral->source }} · {{ $referral->introduced_at?->toDateString() }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-stone-500">Proposed terms</dt><dd>{{ $referral->commission_terms }}</dd></div>
        <div class="sm:col-span-2">
            <dt class="text-stone-500">Consent</dt>
            <dd>
                {{ $referral->consent_note }}
                @if ($referral->privateFile('consent'))
                    <a class="ml-2 font-medium text-lamu-800 hover:underline" href="{{ route('staff.referrals.file', [$referral, 'consent']) }}">Open consent evidence</a>
                @endif
                @if ($referral->privateFile('evidence'))
                    <a class="ml-2 font-medium text-lamu-800 hover:underline" href="{{ route('staff.referrals.file', [$referral, 'evidence']) }}">Open other evidence</a>
                @endif
            </dd>
        </div>
        <div class="sm:col-span-2"><dt class="text-stone-500">Properties</dt><dd>{{ $referral->properties->pluck('title')->join(', ') }}</dd></div>
    </dl>
    <form method="POST" action="{{ route('staff.referrals.update', $referral) }}" class="mt-6 max-w-3xl space-y-3">
        @csrf
        @include('admin.approvals.return-field')
        @method('PUT')
        <label class="block text-sm">
            <span class="font-medium">Decision</span>
            <select name="status" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach ([\App\Enums\BrokerIntroductionStatus::Review, \App\Enums\BrokerIntroductionStatus::Accepted, \App\Enums\BrokerIntroductionStatus::Rejected, \App\Enums\BrokerIntroductionStatus::Disputed] as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">
            <span class="font-medium">Note</span>
            <textarea name="note" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
        </label>
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record decision</button>
    </form>
    <h2 class="mt-8 font-semibold text-lamu-900">History</h2>
    <x-activity-timeline :items="$referral->audits->map(fn ($audit) => [
        'when' => $audit->created_at?->toDayDateTimeString(),
        'text' => trim(($audit->actor?->name ? $audit->actor->name.' · ' : '').$audit->action.($audit->note ? ' — '.$audit->note : '')),
    ])->all()" />
</x-workspace-layout>
