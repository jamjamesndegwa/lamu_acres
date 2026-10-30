<x-auth-layout title="Login — Lamu Acres">
    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Your Lamu Acres account</p>
    <h1 class="home-serif mt-2 text-3xl font-semibold text-lamu-900 sm:text-4xl">Welcome back</h1>
    <p class="mt-2 text-sm text-stone-600">Sign in to manage your saved properties, enquiries and next steps.</p>
    @if ($capability ?? null)
        <p class="mt-4 rounded-xl bg-sand-50 p-4 text-sm text-lamu-900">Sign in to continue your {{ $capability }} application. New here? <a href="{{ route('register', ['capability' => $capability]) }}" class="font-semibold underline">Register as a {{ $capability }}</a>.</p>
    @endif

    @if ($errors->has('session'))
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
            {{ $errors->first('session') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login', array_filter(['capability' => $capability ?? null, 'return_to' => $returnTo ?? null]), false) }}" data-turbo="false" class="mt-8 space-y-4">
        @csrf
        @if ($capability ?? null)
            <input type="hidden" name="capability" value="{{ $capability }}">
        @endif
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <x-password-field name="password" label="Password" required autocomplete="current-password" />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Remember me
        </label>
        <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Sign in</button>
    </form>
    <p class="mt-4 text-right text-sm"><a href="{{ route('password.request') }}" class="font-medium text-lamu-800">Forgot password</a></p>

    <div class="mt-6 border-t border-sand-200 pt-6 text-center">
        <p class="text-sm text-stone-600">New to Lamu Acres?</p>
        <a href="{{ route('register', array_filter(['capability' => $capability ?? null, 'return_to' => $returnTo ?? null])) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-full border border-lamu-900 px-4 py-3 text-sm font-semibold text-lamu-900 hover:bg-sand-50">Create an account</a>
        <p class="mt-4 text-sm text-stone-600">Already registered and want to become a seller or broker? Sign in to open your application.</p>
        <div class="mt-3 flex flex-wrap justify-center gap-4 text-sm font-semibold text-lamu-800">
            <a href="{{ route('account.applications.create', ['capability' => 'seller']) }}" class="underline">Apply as a seller</a>
            <a href="{{ route('account.applications.create', ['capability' => 'broker']) }}" class="underline">Apply as a broker</a>
        </div>
    </div>
    <details class="mt-6 border-t border-sand-200 pt-5" @if ($errors->has('phone')) open @endif>
        <summary class="cursor-pointer text-sm font-medium text-lamu-800">Use phone sign-in instead</summary>
    <form method="POST" action="{{ route('login.phone', absolute: false) }}" data-turbo="false" class="mt-4 space-y-4">
        @csrf
        <h2 class="text-lg font-semibold text-lamu-900">Phone sign-in</h2>
        <p class="text-sm text-stone-600">Enter your phone number to request a sign-in code.</p>
        <div>
            <label for="phone" class="block text-sm font-medium">Phone</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('phone')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="w-full rounded-full border border-lamu-900 px-4 py-3 text-sm font-medium text-lamu-900">Send code</button>
    </form>

    </details>
</x-auth-layout>
