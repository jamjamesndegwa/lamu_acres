<turbo-frame id="modal">
    <x-modal title="Request to buy {{ $property->title }}">
        <p class="text-sm text-stone-600">{{ $property->reference }} · {{ $property->locationLabel() }}</p>
        <p class="mt-2 text-sm text-stone-600">Asking price: {{ $property->formattedPrice() ?: 'Price on request' }}. The sales desk will contact you to discuss your proposal.</p>
        <form method="POST" action="{{ route('land.purchase-requests.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this purchase request to the Lamu Acres sales desk?" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="requested_price" class="block text-sm font-medium">Your proposed total price (KSh)</label>
                <input id="requested_price" name="requested_price" type="number" min="1" step="1" required value="{{ old('requested_price', $property->price_amount) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('requested_price')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="requested_acreage" class="block text-sm font-medium">Acreage requested (optional)</label>
                <input id="requested_acreage" name="requested_acreage" type="number" min="0.0001" max="{{ $property->offeredAcreage() }}" step="0.0001" value="{{ old('requested_acreage') }}" placeholder="Leave blank for the full offered area" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <p class="mt-1 text-xs text-stone-500">Up to {{ rtrim(rtrim(number_format($property->offeredAcreage(), 4, '.', ''), '0'), '.') }} acres are offered. Enter your total price for the area you request.</p>
                @error('requested_acreage')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="purchase-message" class="block text-sm font-medium">Message (optional)</label>
                <textarea id="purchase-message" name="message" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send purchase request</button>
        </form>
    </x-modal>
</turbo-frame>
