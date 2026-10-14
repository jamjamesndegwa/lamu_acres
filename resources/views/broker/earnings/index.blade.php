<x-workspace-layout heading="Earnings and payments">
    <p class="mt-3 max-w-3xl text-stone-700">These are the earned, payable, paid, and disputed amounts staff have already recorded. This page does not calculate a commission.</p>
    @foreach ($groups as $label => $rows)
        <h2 class="mt-8 font-semibold text-lamu-900">{{ $label }}</h2>
        <p class="mt-1 text-sm text-stone-600">Recorded total: KSh {{ number_format($rows->sum(fn ($row) => (int) ($label === 'Paid' && $row->payment ? $row->payment->amount : $row->earned_amount))) }}</p>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($rows as $row)
                <li>
                    <a class="font-medium text-lamu-800 hover:underline" href="{{ route('broker.agreements.show', $row) }}">{{ $row->reference }}</a>
                    · {{ $row->status->label() }}
                    @if ($row->payment)
                        · payment {{ $row->payment->reference }} · {{ $row->payment->status }}
                        @if ($row->payment->paid_at) · paid {{ $row->payment->paid_at->toFormattedDateString() }} @endif
                    @else
                        · no payment record
                    @endif
                </li>
            @empty
                <li class="text-stone-600">Nothing is recorded in this state.</li>
            @endforelse
        </ul>
    @endforeach
    <p class="mt-8 max-w-3xl text-sm text-stone-600">Payment notifications arrive in Notifications. A statement file is shown only when one has been stored. None of the current payment records include a file.</p>
</x-workspace-layout>
