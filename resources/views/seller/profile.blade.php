<x-crud-page title="Seller profile" :close="route('seller.dashboard')">
    <form method="POST" action="{{ route('seller.holding.update') }}" data-turbo-frame="_top" data-confirm="Save this seller profile?" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="legal_name" class="block text-sm font-medium">Legal name</label>
            <input id="legal_name" name="legal_name" value="{{ old('legal_name', $profile?->legal_name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </div>
        <div>
            <label for="correspondence" class="block text-sm font-medium">Correspondence</label>
            <input id="correspondence" name="correspondence" value="{{ old('correspondence', $profile?->correspondence) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </div>
        <div>
            <label for="holding_note" class="block text-sm font-medium">Holding note</label>
            <textarea id="holding_note" name="holding_note" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('holding_note', $profile?->holding_note) }}</textarea>
        </div>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save seller profile</button>
    </form>
</x-crud-page>
