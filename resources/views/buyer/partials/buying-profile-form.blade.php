<form method="POST" action="{{ route('buyer.buying-profile.update') }}" data-turbo-frame="_top" data-confirm="Save this buying profile?" class="space-y-4">
    @csrf
    @method('PUT')
    <div>
        <label for="looking_for" class="block text-sm font-medium">What are you looking for?</label>
        <input id="looking_for" name="looking_for" type="text" value="{{ old('looking_for', $profile?->looking_for) }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('looking_for')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="preferred_locality" class="block text-sm font-medium">Preferred locality</label>
        <input id="preferred_locality" name="preferred_locality" type="text" value="{{ old('preferred_locality', $profile?->preferred_locality) }}" maxlength="120" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('preferred_locality')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save buying profile</button>
</form>
