<x-public-layout title="Choose a new password — Lamu Acres">
    <section class="mx-auto max-w-md px-4 py-16">
        <h1 class="text-2xl font-semibold text-lamu-900">Choose a new password</h1>
        <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">New password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @error('password')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </div>
            <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Update password</button>
        </form>
    </section>
</x-public-layout>
