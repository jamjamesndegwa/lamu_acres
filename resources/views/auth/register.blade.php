@php
    $isModal = \App\Support\Turbo::wantsModal(request());
@endphp

@if ($isModal)
    <turbo-frame id="modal">
        <x-modal title="Create a buyer account">
            <p class="text-sm text-stone-600">Registration opens the buyer workspace. Other workspaces are assigned by an administrator.</p>

            <form method="POST" action="{{ route('register') }}" data-turbo-frame="_top" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="modal-name" class="block text-sm font-medium">Name</label>
                    <input id="modal-name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <div>
                    <label for="modal-register-email" class="block text-sm font-medium">Email</label>
                    <input id="modal-register-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <div>
                    <label for="modal-register-password" class="block text-sm font-medium">Password</label>
                    <input id="modal-register-password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <div>
                    <label for="modal-password-confirmation" class="block text-sm font-medium">Confirm password</label>
                    <input id="modal-password-confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Register</button>
            </form>

            <p class="mt-6 text-sm text-stone-600">
                Already registered?
                <a href="{{ route('login') }}" data-turbo-frame="modal" class="font-medium text-lamu-800">Login</a>
            </p>
        </x-modal>
    </turbo-frame>
@else
    <x-public-layout title="Register — Lamu Acres">
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-semibold text-lamu-900">Create a buyer account</h1>
            <p class="mt-2 text-sm text-stone-600">Registration opens the buyer workspace. Other workspaces are assigned by an administrator.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('name')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('email')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    @error('password')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                </div>
                <button type="submit" class="w-full rounded-full bg-lamu-900 px-4 py-3 text-sm font-medium text-white">Register</button>
            </form>
        </section>
    </x-public-layout>
@endif
