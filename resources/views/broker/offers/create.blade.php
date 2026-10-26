<x-workspace-layout heading="Submit offer for buyer">
    <p class="mt-3 max-w-3xl text-sm text-stone-700">Submit a purchase offer on behalf of a buyer you have introduced. The buyer remains the purchaser; you are recorded as the submitting broker.</p>

    <form method="POST" action="{{ route('broker.offers.store') }}" data-turbo-frame="_top" data-confirm="Send this offer to the Lamu Acres desk?" class="mt-6 max-w-xl space-y-4 rounded-2xl border border-sand-200 bg-white p-5">
        @csrf
        <div>
            <label for="broker_introduction_id" class="block text-sm font-medium">Buyer introduction *</label>
            <select id="broker_introduction_id" name="broker_introduction_id" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Choose introduction</option>
                @foreach ($referrals as $referral)
                    <option value="{{ $referral->id }}" @selected(old('broker_introduction_id', $introduction?->id) == $referral->id)>{{ $referral->reference }} · {{ $referral->buyer?->name ?? $referral->buyer_name }}</option>
                @endforeach
            </select>
            @error('broker_introduction_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="property_id" class="block text-sm font-medium">Property *</label>
            <select id="property_id" name="property_id" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Choose listing</option>
                @foreach ($properties as $listed)
                    <option value="{{ $listed->id }}" @selected(old('property_id') == $listed->id)>{{ $listed->title }} ({{ $listed->reference }})</option>
                @endforeach
            </select>
            @error('property_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="amount" class="block text-sm font-medium">Offer amount (KES) *</label>
            <input id="amount" name="amount" type="number" min="1" required value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('amount')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="requested_acreage" class="block text-sm font-medium">Acreage requested</label>
            <input id="requested_acreage" name="requested_acreage" type="number" min="0.0001" step="0.0001" value="{{ old('requested_acreage') }}" placeholder="Leave blank for the full available area on whole-parcel listings" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('requested_acreage')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="message" class="block text-sm font-medium">Buyer instructions (optional)</label>
            <textarea id="message" name="message" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit offer</button>
    </form>
</x-workspace-layout>
