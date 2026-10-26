@php
    $inventory = $inventory ?? [];
@endphp
<x-workspace-layout :heading="$reference">
    <p class="mt-3 text-sm text-stone-600">{{ $property->title }} · {{ $property->reference }}</p>
    <p class="mt-1 text-sm text-stone-600">Completed {{ $completed_at?->timezone(config('app.timezone'))->format('j M Y') ?? '—' }}</p>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Registered acreage</dt><dd>{{ number_format($inventory['registered_acreage'] ?? (float) $property->acreage, 2) }} acres</dd></div>
        <div><dt class="text-sm text-stone-500">Acreage sold (this sale)</dt><dd>{{ number_format($acreage_sold, 2) }} acres</dd></div>
        <div><dt class="text-sm text-stone-500">Total sold on parcel</dt><dd>{{ number_format($inventory['sold_acreage'] ?? 0, 2) }} acres</dd></div>
        <div><dt class="text-sm text-stone-500">Remaining on parcel</dt><dd>{{ number_format($inventory['remaining_acreage'] ?? $property->remainingAcreage(), 2) }} acres</dd></div>
        <div><dt class="text-sm text-stone-500">Agreed sale amount</dt><dd>KSh {{ number_format($agreed_price ?? 0) }}</dd></div>
        @if ($agency_fee ?? null)
            <div><dt class="text-sm text-stone-500">Agency fee ({{ $agency_fee->reference }})</dt><dd>KSh {{ number_format((int) ($agency_fee->earned_amount ?? 0)) }} payable by {{ $agency_fee->fee_payer->label() }} · Collected KSh {{ number_format((int) $agency_fee->collected_amount) }} · Outstanding KSh {{ number_format($agency_fee->outstandingReceivable()) }}</dd></div>
        @endif
        <div><dt class="text-sm text-stone-500">Buyer</dt><dd>{{ $buyer_name ?? 'Recorded on the sales file' }}</dd></div>
        @if ($sold_portion)
            <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Sold inventory record</dt><dd>{{ $sold_portion->reference }} · {{ number_format((float) $sold_portion->acreage, 2) }} acres</dd></div>
        @endif
    </dl>

    <p class="mt-6 text-sm text-stone-600"><a class="text-lamu-900 underline" href="{{ route('seller.transactions.index') }}">Back to sales records</a></p>
</x-workspace-layout>
