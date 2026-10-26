<form method="POST" action="{{ route('land.offers.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this offer to the Lamu Acres desk?" class="space-y-4">
    @csrf
    <div>
        <label for="{{ $prefix }}amount" class="block text-sm font-medium">Your offer (KES) *</label>
        <input id="{{ $prefix }}amount" name="amount" type="number" min="1" step="1" required value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('amount')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
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
