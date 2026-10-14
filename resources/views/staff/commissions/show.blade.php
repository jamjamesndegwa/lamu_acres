<x-workspace-layout :heading="$agreement->reference">
    <p class="mt-3 text-sm text-stone-600">Version {{ $agreement->version }} · {{ $agreement->status->label() }} · {{ $agreement->funding_party->label() }} funds it</p>
    <p class="mt-1 text-sm">{{ $agreement->broker->name }} · {{ $agreement->property->title }} · due in {{ $agreement->payment_due_days }} days</p>
    <p class="mt-1 text-sm">Qualifying event: {{ $agreement->qualifying_event->label() }}</p>
    @if ($agreement->rate_percent)
        <p class="mt-1 text-sm">Rate {{ $agreement->rate_percent }}%</p>
    @endif
    @if ($agreement->earned_amount)
        <p class="mt-1 text-sm">Earned KSh {{ number_format($agreement->earned_amount) }}</p>
    @endif

    @if ($agreement->status === \App\Enums\CommissionStatus::Proposed)
        <form method="POST" action="{{ route('staff.commissions.accept', $agreement) }}" data-turbo-frame="_top" data-confirm="Accept {{ $agreement->reference }} for {{ $agreement->broker->name }}. Rate {{ $agreement->rate_percent ?: 'fixed '.$agreement->fixed_amount }}. Resulting state: Accepted. Accepting does not earn or pay commission." class="mt-4">
            @csrf
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Accept agreement</button>
        </form>
    @endif
    <form method="POST" action="{{ route('staff.commissions.revise', $agreement) }}" data-turbo-frame="_top" data-confirm="Revise this agreement?" class="mt-4 flex flex-wrap gap-2">
        @csrf
        <input name="rate_percent" placeholder="Revised rate" class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="fixed_amount" placeholder="Revised fixed amount" class="rounded-lg border border-sand-200 px-3 py-2">
        <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Revise</button>
    </form>
    <form method="POST" action="{{ route('staff.commissions.dispute', $agreement) }}" data-turbo-frame="_top" data-confirm="Dispute this commission claim?" class="mt-4 grid max-w-xl gap-2">
        @csrf
        <textarea name="reason" required minlength="8" class="rounded-lg border border-sand-200 px-3 py-2" placeholder="Dispute reason"></textarea>
        <button class="rounded-full border border-sand-200 px-4 py-2 text-sm">Record dispute</button>
    </form>

    <h2 class="mt-8 font-semibold text-lamu-900">Version history</h2>
    <ol class="mt-2 space-y-1 text-sm">
        @foreach ($versions as $version)
            <li><a class="text-lamu-800 hover:underline" href="{{ route('staff.commissions.show', $version) }}">Version {{ $version->version }} · {{ $version->status->label() }}</a></li>
        @endforeach
    </ol>
</x-workspace-layout>
