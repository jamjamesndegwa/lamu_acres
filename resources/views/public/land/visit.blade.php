<turbo-frame id="modal">
    <x-modal title="Book a site visit">
        <p class="text-sm text-stone-600">{{ $property->title }}. The desk confirms the time. This form does not share a parcel boundary.</p>
        <form method="POST" action="{{ route('land.visits.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this site visit request?" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="preferred_on" class="block text-sm font-medium">Preferred date</label>
                <input id="preferred_on" type="date" name="preferred_on" required value="{{ old('preferred_on') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('preferred_on')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="visit-note" class="block text-sm font-medium">Note</label>
                <textarea id="visit-note" name="note" rows="3" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send request</button>
        </form>
        <p class="mt-4 text-xs text-stone-500">Confirmation appears in your in-app notifications. We attempt email when email updates are enabled and mail delivery is configured. SMS requires SMS updates to be enabled and an active Lamu Acres SMS provider.</p>
    </x-modal>
</turbo-frame>
