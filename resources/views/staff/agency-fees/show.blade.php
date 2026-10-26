<x-workspace-layout :heading="$agreement->reference">
    <p class="mt-3 text-sm text-stone-600">Version {{ $agreement->version }} · {{ $agreement->status->label() }} · {{ $agreement->fee_payer->label() }} pays</p>
    <p class="mt-1 text-sm">{{ $agreement->seller->name }} · {{ $agreement->property->title }}</p>
    @if ($agreement->rate_percent)
        <p class="mt-1 text-sm">Rate {{ $agreement->rate_percent }}% of {{ $agreement->calculation_basis->label() }}</p>
    @endif
    @if ($agreement->earned_amount)
        <p class="mt-1 text-sm">Earned KSh {{ number_format($agreement->earned_amount) }} · Collected KSh {{ number_format($agreement->collected_amount) }} · Outstanding KSh {{ number_format($agreement->outstandingReceivable()) }}</p>
    @endif
    @if ($agreement->status === \App\Enums\CommissionStatus::Proposed && auth()->user()->hasPermission('agency.propose'))
        <form method="POST" action="{{ route('staff.agency-fees.accept', $agreement) }}" data-turbo-frame="_top" data-confirm="Accept this agency-fee agreement?" class="mt-4">
            @csrf
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Accept agreement</button>
        </form>
    @endif
</x-workspace-layout>
