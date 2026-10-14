<form
    method="POST"
    action="{{ route($workspace->value.'.profile.update') }}"
    @if ($breakout ?? false) data-turbo-frame="_top" @endif
    data-confirm="Save these profile changes?"
    class="space-y-4"
>
    @csrf
    @method('PUT')

    <div>
        <label for="{{ $prefix }}name" class="block text-sm font-medium">Name</label>
        <input id="{{ $prefix }}name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('name')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="{{ $prefix }}email" class="block text-sm font-medium">Email</label>
        <input id="{{ $prefix }}email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('email')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @if ($user->email_verified_at === null)
            <p class="mt-1 text-sm text-stone-600">This email is not confirmed yet.</p>
        @endif
    </div>
    <div>
        <label for="{{ $prefix }}phone" class="block text-sm font-medium">Phone</label>
        <input id="{{ $prefix }}phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('phone')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @if ($user->pending_phone)
            <p class="mt-1 text-sm text-stone-600">A code was requested for {{ $user->pending_phone }}. The saved number stays the same until that code is confirmed.</p>
        @endif
    </div>
    <div>
        <label for="{{ $prefix }}current_password" class="block text-sm font-medium">Current password</label>
        <input id="{{ $prefix }}current_password" name="current_password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('current_password')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="{{ $prefix }}password" class="block text-sm font-medium">New password</label>
        <input id="{{ $prefix }}password" name="password" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
        @error('password')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="{{ $prefix }}password_confirmation" class="block text-sm font-medium">Confirm new password</label>
        <input id="{{ $prefix }}password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
    </div>
    <input type="hidden" name="notify_by_email" value="0">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="notify_by_email" value="1" @checked(old('notify_by_email', $user->notify_by_email ?? true))>
        Email me account and application updates
    </label>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save profile</button>
</form>
