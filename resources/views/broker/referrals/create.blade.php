<x-crud-page title="Register buyer" :close="route('broker.referrals.index')" size="xl">
    <p class="mt-3 max-w-3xl text-stone-700">A referral is submitted for staff review. A matching or earlier introduction is recorded, not rejected, and it does not award commission.</p>
    <form method="POST" action="{{ route('broker.referrals.store') }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Register this buyer?" class="mt-6 max-w-3xl space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="font-medium">Buyer name</span>
            <input name="buyer_name" value="{{ old('buyer_name') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Buyer email</span>
                <input name="buyer_email" type="email" value="{{ old('buyer_email') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Buyer phone</span>
                <input name="buyer_phone" value="{{ old('buyer_phone') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
        </div>
        <fieldset>
            <legend class="text-sm font-medium">Published properties</legend>
            <div class="mt-2 space-y-2">
                @forelse ($properties as $property)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="property_ids[]" value="{{ $property->id }}" @checked(in_array($property->id, array_map('intval', (array) old('property_ids', [])), true))>
                        <span>{{ $property->title }} <span class="text-stone-500">{{ $property->reference }} · {{ $property->locality }}</span></span>
                    </label>
                @empty
                    <p class="text-sm text-stone-600">No published property is available to attach.</p>
                @endforelse
            </div>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Referral date</span>
                <input name="introduced_at" type="date" value="{{ old('introduced_at', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Source</span>
                <input name="source" value="{{ old('source') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
        </div>
        <label class="block text-sm">
            <span class="font-medium">Proposed commission terms</span>
            <textarea name="commission_terms" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('commission_terms') }}</textarea>
        </label>
        <label class="block text-sm">
            <span class="font-medium">Consent and authority</span>
            <textarea name="consent_note" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('consent_note') }}</textarea>
        </label>
        <label class="block text-sm">
            <span class="font-medium">Consent evidence</span>
            <input name="consent_evidence" type="file" accept="image/*,.pdf" required class="mt-1 block w-full text-sm">
        </label>
        <label class="block text-sm">
            <span class="font-medium">Other evidence</span>
            <input name="evidence" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm">
        </label>
        <label class="block text-sm">
            <span class="font-medium">Notes</span>
            <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('notes') }}</textarea>
        </label>
        @if ($errors->any())
            <p class="text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit referral</button>
    </form>
</x-crud-page>
