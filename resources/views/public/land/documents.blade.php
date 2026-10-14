<turbo-frame id="modal">
    <x-modal title="Request documents">
        <p class="text-sm text-stone-600">{{ $property->title }}. The desk reviews the request. Original files are not downloaded from this form.</p>
        <form method="POST" action="{{ route('land.documents.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this document request?" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="document-note" class="block text-sm font-medium">What do you need?</label>
                <textarea id="document-note" name="note" rows="4" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send request</button>
        </form>
    </x-modal>
</turbo-frame>
