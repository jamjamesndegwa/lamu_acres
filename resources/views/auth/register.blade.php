<x-auth-layout title="Register — Lamu Acres">
    @php
        $selectedCapability = in_array(old('capability', $capability ?? null), ['seller', 'broker'], true) ? old('capability', $capability ?? null) : null;
    @endphp
    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Join Lamu Acres</p>
    <h1 class="home-serif mt-2 text-3xl font-semibold text-lamu-900">{{ $selectedCapability ? 'Register as a '.$selectedCapability : 'Create your account' }}</h1>
    <p class="mt-2 text-sm text-stone-600">Buy land, apply to list your property as a seller, or apply to work with us as a broker.</p>
    <div class="mt-5 rounded-xl border border-sand-200 bg-sand-50 p-4 text-sm text-stone-700">
        <p class="font-semibold text-lamu-900">Registering as a seller or broker?</p>
        <p class="mt-2">{{ $selectedCapability ? 'Step 1: Create your account below. Step 2: Complete your '.$selectedCapability.' application and upload your evidence. Step 3: Staff verifies your application before granting access.' : 'Choose seller or broker below to continue directly to your application after creating your account. Staff verification is required before access is granted.' }}</p>
    </div>
    <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-lamu-800">
        <a href="{{ route('register', ['capability' => 'seller']) }}" class="underline">Register as a seller</a>
        <a href="{{ route('register', ['capability' => 'broker']) }}" class="underline">Register as a broker</a>
    </div>

    <form method="POST" action="{{ route('register', array_filter(['return_to' => $returnTo ?? null])) }}" class="mt-8 space-y-4">
        @csrf
        @if ($selectedCapability)
            <input type="hidden" name="capability" value="{{ $selectedCapability }}">
        @endif
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
            <label for="phone" class="block text-sm font-medium">Phone <span class="font-normal text-stone-500">(optional, for SMS updates)</span></label>
            <input id="phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel" class="mt-2 h-12 w-full rounded-lg border border-sand-200 bg-white px-3 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100">
            @error('phone')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <fieldset class="space-y-3 rounded-xl border border-sand-200 bg-sand-50/80 p-4">
            <legend class="px-1 text-sm font-semibold text-lamu-900">Stay in touch</legend>
            <p class="text-xs text-stone-600">Optional. You can change these anytime in your profile. Account emails (applications, offers, visits) still send when needed.</p>
            <input type="hidden" name="marketing_email_consent" value="0">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="marketing_email_consent" value="1" @checked(old('marketing_email_consent')) class="mt-1">
                <span>Email me Lamu Acres news and land opportunities</span>
            </label>
            <input type="hidden" name="marketing_sms_consent" value="0">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="marketing_sms_consent" value="1" @checked(old('marketing_sms_consent')) class="mt-1">
                <span>SMS me Lamu Acres news and land opportunities (requires a phone number)</span>
            </label>
        </fieldset>
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
        <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">{{ $selectedCapability ? 'Create account & continue to '.$selectedCapability.' application' : 'Create account' }}</button>
    </form>

    <p class="mt-6 text-sm text-stone-600">Already have an account? <a href="{{ route('login', array_filter(['capability' => $selectedCapability, 'return_to' => $returnTo ?? null])) }}" class="font-medium text-lamu-800">{{ $selectedCapability ? 'Sign in to continue your '.$selectedCapability.' application' : 'Sign in' }}</a></p>
</x-auth-layout>
