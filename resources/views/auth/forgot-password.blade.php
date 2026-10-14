<x-auth-layout title="Forgot password — Lamu Acres">
    <h1 class="text-2xl font-semibold text-lamu-900">Reset password</h1>
    <p class="mt-2 text-sm text-stone-600">For accounts that sign in with a password. A reset link is sent to the email on file.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Send reset link</button>
    </form>
    <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="font-medium text-lamu-800">Back to login</a></p>
</x-auth-layout>
