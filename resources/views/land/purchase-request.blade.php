<turbo-frame id="modal">
    <x-modal title="I'm interested in buying">
        <p class="text-sm text-stone-600">{{ $property->title }} · {{ $property->reference }} · {{ $property->locationLabel() }}</p>
        <p class="mt-2 text-sm text-stone-600">Asking price: {{ $property->formattedPrice() ?: 'Price on request' }}. This records your interest and proposed terms. It is not a binding purchase agreement.</p>
        <p class="mt-2 text-sm text-stone-600">{{ $property->saleMode()->label() }} · {{ rtrim(rtrim(number_format($inventory['available_acreage'], 4, '.', ''), '0'), '.') }} acres currently available@if ($property->allowsPartialPurchases() && $property->minimum_acreage) · minimum {{ rtrim(rtrim(number_format((float) $property->minimum_acreage, 4, '.', ''), '0'), '.') }} acres @endif.</p>
        @unless ($property->allowsPartialPurchases())
            <p class="mt-2 text-sm text-amber-900">Purchase requests must cover the full {{ rtrim(rtrim(number_format($inventory['available_acreage'], 4, '.', ''), '0'), '.') }} acres. For a smaller area, send a general enquiry instead.</p>
        @endunless
        <form
            method="POST"
            action="{{ route('land.purchase-requests.store', $property) }}"
            data-turbo-frame="_top"
            data-confirm="Send this purchase interest to the Lamu Acres sales desk?"
            data-purchase-pricing
            data-price-basis="{{ $property->priceBasis()->value }}"
            data-price-rate="{{ $property->price_rate ?? '' }}"
            data-price-amount="{{ $property->price_amount ?? '' }}"
            data-offered-acreage="{{ $inventory['offered_acreage'] }}"
            data-available-acreage="{{ $inventory['available_acreage'] }}"
            data-whole-acreage="{{ $property->allowsPartialPurchases() ? '' : $inventory['available_acreage'] }}"
            class="mt-5 space-y-4"
        >
            @csrf
            <div>
                <label for="requested_price" class="block text-sm font-medium">Your proposed total price (KSh)</label>
                <input id="requested_price" name="requested_price" type="number" min="1" step="1" required value="{{ old('requested_price', $property->price_amount) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('requested_price')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            @if ($property->allowsPartialPurchases())
                <div>
                    <label for="requested_acreage" class="block text-sm font-medium">Acreage requested</label>
                    <input id="requested_acreage" name="requested_acreage" type="number" min="{{ $property->minimum_acreage ?? 0.0001 }}" max="{{ $inventory['available_acreage'] }}" step="0.0001" value="{{ old('requested_acreage') }}" placeholder="Leave blank for the full available area" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" data-purchase-acreage>
                    <p class="mt-1 text-xs text-stone-500" data-purchase-acreage-hint>Enter your total proposed price for the acreage you request. Indicative asking total updates as you type.</p>
                    <p class="mt-1 text-xs text-stone-600" data-purchase-indicative-price hidden></p>
                    @error('requested_acreage')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="requested_acreage" value="{{ $inventory['available_acreage'] }}">
                <p class="mt-1 text-xs text-stone-600" data-purchase-indicative-price hidden></p>
            @endif
            <div>
                <label for="purchase-message" class="block text-sm font-medium">Message (optional)</label>
                <textarea id="purchase-message" name="message" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send purchase interest</button>
        </form>
    </x-modal>
</turbo-frame>
