@php
    $inventory = $inventory ?? $property->inventorySnapshot();
@endphp
<form method="POST" action="{{ route('land.offers.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this offer to the Lamu Acres desk?" class="space-y-4">
    @csrf
    <div>
        <label for="{{ $prefix }}amount" class="block text-sm font-medium">Your offer (KES) *</label>
        <input id="{{ $prefix }}amount" name="amount" type="number" min="1" step="1" required value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('amount')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    @if ($property->allowsPartialPurchases())
        <div>
            <label for="{{ $prefix }}requested_acreage" class="block text-sm font-medium">Acreage requested</label>
            <input id="{{ $prefix }}requested_acreage" name="requested_acreage" type="number" min="{{ $property->minimum_acreage ?? 0.0001 }}" max="{{ $inventory['available_acreage'] }}" step="0.0001" value="{{ old('requested_acreage') }}" placeholder="Leave blank for the full available area" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <p class="mt-1 text-xs text-stone-500">{{ rtrim(rtrim(number_format($inventory['available_acreage'], 4, '.', ''), '0'), '.') }} acres currently available.</p>
            @error('requested_acreage')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
    @else
        <input type="hidden" name="requested_acreage" value="{{ $inventory['available_acreage'] }}">
        <p class="text-xs text-stone-600">This listing is offered as one parcel ({{ rtrim(rtrim(number_format($inventory['available_acreage'], 4, '.', ''), '0'), '.') }} acres).</p>
    @endif
    <div>
        <label for="{{ $prefix }}message" class="block text-sm font-medium">Message (optional)</label>
        <textarea id="{{ $prefix }}message" name="message" rows="4" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('message') }}</textarea>
        @error('message')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <p class="text-sm text-stone-600">Lamu Acres will review your offer and contact you. This does not reserve or purchase the land.</p>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit offer</button>
</form>
