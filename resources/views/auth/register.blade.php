<x-auth-layout title="Register — Lamu Acres">
    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Start exploring</p>
    <h1 class="home-serif mt-2 text-3xl font-semibold text-lamu-900">Create a buyer account</h1>
    <p class="mt-2 text-sm text-stone-600">Save your favourite properties, contact the Lamu Acres desk and arrange your next site visit.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('name')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('password')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
        </div>
        <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Create buyer account</button>
    </form>

    <p class="mt-6 text-sm text-stone-600">Already have an account? <a href="{{ route('login') }}" class="font-medium text-lamu-800">Sign in</a></p>
</x-auth-layout>
