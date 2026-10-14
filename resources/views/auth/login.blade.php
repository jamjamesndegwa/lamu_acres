@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Login">
            <p class="text-sm text-stone-600">Use the email and password for your Lamu Acres account.</p>

            <form method="POST" action="{{ route('login') }}" data-turbo-frame="_top" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="modal-email" class="block text-sm font-medium">Email</label>
                    <input id="modal-email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <div>
                    <label for="modal-password" class="block text-sm font-medium">Password</label>
                    <input id="modal-password" name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Remember me
                </label>
                <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Login</button>
            </form>
            <p class="mt-3 text-sm"><a href="{{ route('password.request') }}" data-turbo-frame="_top" class="font-medium text-lamu-800">Forgot password</a></p>

            <form method="POST" action="{{ route('login.phone') }}" data-turbo-frame="_top" class="mt-8 space-y-4 border-t border-sand-200 pt-6">
                @csrf
                <h3 class="font-semibold text-lamu-900">Phone sign-in</h3>
                <p class="text-sm text-stone-600">For public customers. A code is sent only when a delivery provider is configured.</p>
                <div>
                    <label for="modal-phone" class="block text-sm font-medium">Phone</label>
                    <input id="modal-phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <button type="submit" class="w-full rounded-full border border-lamu-900 px-4 py-3 text-sm font-medium text-lamu-900">Send code</button>
            </form>

            <p class="mt-6 text-sm text-stone-600">
                No account yet?
                <a href="{{ route('register') }}" data-turbo-frame="modal" class="font-medium text-lamu-800">Register as a buyer</a>
            </p>
        </x-modal>
    </turbo-frame>
@else
    <x-public-layout title="Login — Lamu Acres">
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-semibold text-lamu-900">Login</h1>
            <p class="mt-2 text-sm text-stone-600">Use the email and password for your Lamu Acres account.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('email')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('password')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Remember me
                </label>
                <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Login</button>
            </form>
            <p class="mt-3 text-sm"><a href="{{ route('password.request') }}" class="font-medium text-lamu-800">Forgot password</a></p>

            <form method="POST" action="{{ route('login.phone') }}" class="mt-10 space-y-4 border-t border-sand-200 pt-8">
                @csrf
                <h2 class="text-lg font-semibold text-lamu-900">Phone sign-in</h2>
                <p class="text-sm text-stone-600">For public customers. A code is sent only when a delivery provider is configured.</p>
                <div>
                    <label for="phone" class="block text-sm font-medium">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" autocomplete="tel" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="w-full rounded-full border border-lamu-900 px-4 py-3 text-sm font-medium text-lamu-900">Send code</button>
            </form>

            <p class="mt-6 text-sm text-stone-600">No account yet? <a href="{{ route('register') }}" class="font-medium text-lamu-800">Register as a buyer</a></p>
        </section>
    </x-public-layout>
@endif
