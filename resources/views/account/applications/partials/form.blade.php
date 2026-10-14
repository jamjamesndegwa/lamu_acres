<form method="POST" action="{{ route('account.applications.store') }}" data-turbo-frame="_top" data-confirm="Submit this application?" class="space-y-4">
    @csrf
    <input type="hidden" name="capability" value="{{ $capability->value }}">
    <div>
        <label for="organization" class="block text-sm font-medium">Organisation</label>
        <input id="organization" name="organization" type="text" value="{{ old('organization', $application?->organization) }}" maxlength="160" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('organization')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="statement" class="block text-sm font-medium">Short statement</label>
        <textarea id="statement" name="statement" rows="4" required maxlength="1000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('statement', $application?->statement) }}</textarea>
        @error('statement')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @error('capability')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit application</button>
</form>
