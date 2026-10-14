<x-workspace-layout :heading="$deal->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $deal->stage->label() }}@if ($deal->outcome) · {{ $deal->outcome->label() }}@endif · {{ $deal->property?->title }}</p>
    <p class="mt-3 max-w-3xl text-sm text-stone-700">Introduction {{ $deal->introduction?->reference }} for {{ $deal->introduction?->buyer_name }}. You can follow the milestones staff have recorded. You cannot move the stage or confirm closing.</p>
    <h2 class="mt-8 font-semibold text-lamu-900">Milestones</h2>
    <ul class="mt-3 space-y-1 text-sm">
        @forelse ($deal->milestones as $milestone)
            <li>{{ $milestone->name }} @if ($milestone->due_on) · due {{ $milestone->due_on->toFormattedDateString() }} @endif @if ($milestone->completed_at) · completed {{ $milestone->completed_at->toFormattedDateString() }} @else · not completed @endif</li>
        @empty
            <li class="text-stone-600">No milestone has been recorded on this deal.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 font-semibold text-lamu-900">Accepted commission terms</h2>
    <ul class="mt-3 space-y-1 text-sm">
        @forelse ($deal->agreements as $agreement)
            @if (in_array($agreement->status, [\App\Enums\CommissionStatus::Accepted, \App\Enums\CommissionStatus::Earned, \App\Enums\CommissionStatus::Payable, \App\Enums\CommissionStatus::Paid, \App\Enums\CommissionStatus::Disputed], true))
                <li><a class="font-medium text-lamu-800 hover:underline" href="{{ route('broker.agreements.show', $agreement) }}">{{ $agreement->reference }}</a> · {{ $agreement->status->label() }}</li>
            @endif
        @empty
            <li class="text-stone-600">No accepted commission terms are linked to this deal.</li>
        @endforelse
    </ul>
</x-workspace-layout>
